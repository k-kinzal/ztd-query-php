<?php

declare(strict_types=1);

namespace Deriver\Source\Declaration;

use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\CallableIdentity;
use Deriver\ControlFlow\ClassDeclaration;
use Deriver\ControlFlow\Program;
use Deriver\Exception\InvalidInputException;
use Deriver\Project\ProjectInput;
use Deriver\Project\SourceFile;
use Deriver\Project\SourceLimits;
use Deriver\Project\TargetProfile;
use Deriver\Reference\SourceRef;
use Deriver\Result\Frontier;
use Deriver\Source\Cache\GraphCache;
use Deriver\Source\Cache\SyntaxCache;
use Deriver\Source\Compilation\CallableCompiler;
use Deriver\Source\Compilation\GraphBuilder;
use Deriver\Source\Compilation\Lowering;
use Deriver\Source\Declaration\Traits\Composition;
use Deriver\Source\LineMap;
use Override;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

/**
 * Captures declarations eagerly and compiles callable control flow on demand.
 * @visibility root
 */
final class ProjectIndex implements Program
{
    /**
     * @var array<string, SourceFile> Captured source by normalized path.
     */
    public array $files = [];
    /**
     * @var array<string, string> Source hashes computed once per captured file
     */
    public array $fileHashes = [];
    /**
     * @var array<string, LineMap> Source positions shared by all lowered bodies
     */
    public array $lineMaps = [];
    /**
     * @var array<string, CallableSource> Uncompiled declarations.
     */
    public array $declarations = [];
    /**
     * @var array<string, ClassDeclaration> Static class index.
     */
    public array $classIndex = [];
    /**
     * @var array<string, CallableSource> Original class-like declarations used for trait composition
     */
    public array $classSources = [];
    /**
     * @var list<Frontier> Project diagnostics.
     */
    public array $issues = [];
    /**
     * @var array<string, CallableGraph> Lazily compiled graphs.
     */
    public array $graphs = [];
    /**
     * @var array<string, CallableSource> Unevaluated constant initializers.
     */
    public array $constantSources = [];
    /**
     * @var array<string, CallableSource>|null Lexical declarations captured by an active lowering transaction
     */
    public ?array $capturedClosures = null;
    /**
     * Total admitted syntax nodes across all files.
     */
    public int $syntaxNodes = 0;

    /**
     * @param string $snapshotId Manifest identity
     * @param ProjectInput $input Source contents
     * @param TargetProfile $profile Target PHP semantics
     * @param SyntaxCache $syntax Shared bounded syntax cache
     * @param GraphCache $lowered Shared bounded source-IR cache
     * @param SourceLimits $limits Deterministic source admission policy
     * @throws InvalidInputException If the complete project exceeds admission limits
     */
    public function __construct(public readonly string $snapshotId, ProjectInput $input, public readonly TargetProfile $profile, public readonly SyntaxCache $syntax = new SyntaxCache(), public readonly GraphCache $lowered = new GraphCache(), public readonly SourceLimits $limits = new SourceLimits())
    {
        $limits->check($input);
        foreach ($input->files as $file) {
            $path = ProjectInput::normalize($file->path);
            $this->files[$path] = new SourceFile($path, $file->contents, $file->declarationsOnly);
            $this->fileHashes[$path] = hash('sha256', $file->contents);
            $this->lineMaps[$path] = new LineMap($file->contents);
        }
        ksort($this->files);
        ksort($this->fileHashes);
        $ordered = array_values($this->files);
        usort($ordered, static fn (SourceFile $a, SourceFile $b): int => [$a->declarationsOnly, $a->path] <=> [$b->declarationsOnly, $b->path]);
        foreach ($ordered as $file) {
            $this->parse($file, $profile);
        }
        $traits = new Composition($this, hash('sha256', serialize($this->fileHashes)));
        foreach (array_keys($this->classIndex) as $class) {
            $traits->compose($class);
        }
        ksort($this->declarations);
        ksort($this->classIndex);
    }

    /**
     * Parses source bytes without executing user PHP.
     * @param SourceFile $file Captured source
     * @param TargetProfile $profile Grammar profile
     * @throws InvalidInputException If parser output violates its top-level contract
     */
    public function parse(SourceFile $file, TargetProfile $profile): void
    {
        $tree = $this->syntax->read($file, $profile, $this->limits);
        $this->syntaxNodes += $tree->size;
        if ($this->syntaxNodes > $this->limits->nodes) {
            throw new InvalidInputException('SOURCE_LIMIT: project syntax exceeds the admission limit.');
        }
        if ($tree->errors !== []) {
            foreach ($tree->errors as $error) {
                $at = new SourceRef($this->snapshotId, $file->path, 0, strlen($file->contents), $error['line']);
                $this->issues[] = new Frontier('INCOMPLETE_SOURCE', $at, $error['message'], missingCapability: 'valid-php-source');
            }
            return;
        }
        $nodes = $tree->nodes;
        $script = new Stmt\Namespace_(null, $nodes, ['startFilePos' => 0, 'endFilePos' => strlen($file->contents) - 1, 'startLine' => 1]);
        $strict = (new DeclarationScanner($this))->strict($nodes);
        if (!$file->declarationsOnly) {
            $this->register(new CallableSource('script:' . $file->path, $script, $file->path, strict: $strict));
        }
        (new DeclarationScanner($this))->scan($nodes, $file->path, $strict);
    }

    /**
     * Registers a declaration and reports duplicate names instead of choosing one.
     * @param CallableSource $source Captured callable
     */
    public function register(CallableSource $source): void
    {
        $key = (new CallableIdentity())->key($source->symbol);
        if (isset($this->declarations[$key])) {
            if ($this->files[$source->path]->declarationsOnly && !$this->files[$this->declarations[$key]->path]->declarationsOnly) {
                return;
            }
            $this->issues[] = new Frontier('INVALID_PROGRAM', $this->builder($source->path)->source($source->node), 'duplicate:' . $source->symbol);
            return;
        }
        $this->declarations[$key] = $source;
    }

    /**
     * Creates a graph builder associated with captured source bytes.
     * @param string $path Captured source path
     * @return GraphBuilder New graph builder
     */
    public function builder(string $path): GraphBuilder
    {
        return new GraphBuilder($this->snapshotId, $path, $this->files[$path]->contents, $this->lineMaps[$path]);
    }

    /**
     * Compiles a requested source body once per immutable snapshot.
     * @param string $symbol Callable identity
     * @return CallableGraph|null Available graph
     */
    #[Override]
    public function callable(string $symbol): ?CallableGraph
    {
        $key = (new CallableIdentity())->key($symbol);
        if (isset($this->graphs[$key])) {
            return $this->graphs[$key];
        }
        $source = $this->declarations[$key] ?? null;
        if ($source === null) {
            return null;
        }
        return $this->graphs[$key] = $this->lowered->read($this, $source);
    }

    /**
     * Registers a lexical closure without executing its body.
     * @param Expr\Closure|Expr\ArrowFunction $node Closure expression
     * @param string $path Source path
     * @param string $className Lexical class
     * @return string Stable closure identity
     */
    public function registerClosure(Expr\Closure|Expr\ArrowFunction $node, string $path, string $className): string
    {
        $symbol = 'closure:' . $path . ':' . $node->getStartFilePos() . ($className === '' ? '' : ':scope:' . $className);
        if (!isset($this->declarations[(new CallableIdentity())->key($symbol)])) {
            $this->register(new CallableSource($symbol, $node, $path, $className, $this->declarations['script:' . $path]->strict ?? false));
        }
        if ($this->capturedClosures !== null) {
            $this->capturedClosures[(new CallableIdentity())->key($symbol)] = $this->declarations[(new CallableIdentity())->key($symbol)];
        }
        return $symbol;
    }

    /**

     * @return array<string, ClassDeclaration> Class declarations.

     */
    #[Override]
    public function classes(): array
    {
        return $this->classIndex;
    }

    /**

     * @return list<string> Stable callable identities.

     */
    #[Override]
    public function symbols(): array
    {
        $symbols = array_map(static fn (CallableSource $source): string => $source->symbol, array_values($this->declarations));
        sort($symbols);
        return $symbols;
    }

    /**

     * @return list<Frontier> Source diagnostics.

     */
    #[Override]
    public function diagnostics(): array
    {
        return $this->issues;
    }

    /**

     * @return int Materialized callable count.

     */
    #[Override]
    public function graphCount(): int
    {
        return count($this->graphs);
    }
    /**
     * Compiles a global or class constant only when its value is demanded.
     * @param string $symbol Case-sensitive constant identity
     * @return CallableGraph|null Captured initializer
     */
    #[Override]
    public function constant(string $symbol): ?CallableGraph
    {
        $parts = explode('::', $symbol, 2);
        $key = count($parts) === 2 ? strtolower($parts[0]) . '::' . $parts[1] : $symbol;
        $source = $this->constantSources[$key] ?? null;
        if ($source === null || !$source->node instanceof Expr) {
            return null;
        }
        return $this->graphs['constant:' . $key] ??= (new CallableCompiler($this))->expression($source->node, $source->path, 'constant:' . $symbol, $source->className);
    }
    /**
     * Identifies source owners without eagerly lowering unrelated functions.
     * @param string $symbol Function or method selector
     * @return list<string> Candidate callable identities
     */
    #[Override]
    public function callOwners(string $symbol): array
    {
        return (new CallSiteIndex($this))->owners($symbol);
    }
}
