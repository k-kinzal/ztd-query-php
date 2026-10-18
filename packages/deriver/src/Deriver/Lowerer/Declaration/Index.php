<?php

declare(strict_types=1);

namespace Deriver\Deriver\Lowerer\Declaration;

use Deriver\Deriver\Lowerer\Declaration\Diagnostic as Frontier;
use Deriver\Deriver\Lowerer\Declaration\Metadata\ClassDeclaration;
use Deriver\Deriver\Lowerer\Declaration\Traits\Composition;
use Deriver\Deriver\Lowerer\Graph\CallableGraph;
use Deriver\Deriver\Lowerer\Graph\Program;
use Deriver\Deriver\Lowerer\InstructionEmitter;
use Deriver\Deriver\Lowerer\Lowerer;
use Deriver\Deriver\Sources\LineMap;
use Deriver\Deriver\Sources\Path;
use Deriver\Deriver\Sources\Source;
use Override;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;

/**
 * Captures declarations eagerly and compiles callable control flow on demand.
 * @visibility Deriver
 * @visibility Deriver\Deriver\Lowerer\Declaration
 * @visibility Deriver\Deriver\Lowerer
 * @visibility Deriver\Deriver\Sources
 * @visibility Deriver\Deriver\Selection
 * @visibility Deriver\Deriver
 * @visibility Deriver\Deriver
 */
final class Index implements Program
{
    /**
     * Snapshot-local reverse indexes, built before any query is evaluated.
     */
    public readonly References $references;
    /**
     * @var list<string>|null Sorted declaration spellings, invalidated by registration
     */
    private ?array $symbolNames = null;
    private bool $indexReferences = false;
    /**
     * @var array<string, Source> Captured source by normalized path.
     */
    public array $files = [];
    /**
     * @var array<string, LineMap> \Deriver\Deriver\Sources\Source positions shared by all lowered bodies
     */
    public array $lineMaps = [];
    /**
     * @var array<string, CallableSource> Uncompiled declarations.
     */
    public array $declarations = [];
    /**
     * @var array<string, list<string>> Alternative conditional declarations
     */
    public array $conditionalDeclarations = [];
    /**
     * @var array<string, ClassDeclaration> Static class index.
     */
    public array $classIndex = [];
    /**
     * @var array<string, CallableSource> Original class-like declarations used for trait composition
     */
    public array $classSources = [];
    /**
     * @var list<Frontier> Declaration diagnostics.
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
     * @param string $snapshotId Manifest identity
     * @param list<\Deriver\Deriver\Parser\Node\File> $trees Resolved source trees
     */
    public function __construct(public readonly string $snapshotId, array $trees)
    {
        $this->references = new References($this);
        foreach ($trees as $tree) {
            $file = $tree->source;
            $path = Path::normalize($file->path);
            $this->files[$path] = new Source($path, $file->contents, $file->declarationsOnly);
            $this->lineMaps[$path] = new LineMap($file->contents);
        }
        ksort($this->files);
        $ordered = $trees;
        usort($ordered, static fn (\Deriver\Deriver\Parser\Node\File $a, \Deriver\Deriver\Parser\Node\File $b): int => [$a->source->declarationsOnly, $a->source->path] <=> [$b->source->declarationsOnly, $b->source->path]);
        foreach ($ordered as $tree) {
            $this->scan($tree);
        }
        $traits = new Composition($this);
        foreach (array_keys($this->classIndex) as $class) {
            $traits->compose($class);
        }
        ksort($this->declarations);
        ksort($this->classIndex);
        $this->indexReferences = true;
        foreach ($this->declarations as $source) {
            $this->references->add($source);
        }
        ksort($this->declarations);
    }

    /**
     * Reads the parsed file and indexes its declarations without executing user PHP.
     * @param \Deriver\Deriver\Parser\Node\File $tree Resolved source tree
     */
    public function scan(\Deriver\Deriver\Parser\Node\File $tree): void
    {
        $file = $tree->source;
        $nodes = $tree->stmts;
        $script = new Stmt\Namespace_(null, $nodes, ['startFilePos' => 0, 'endFilePos' => strlen($file->contents) - 1, 'startLine' => 1]);
        $strict = (new Indexer($this))->strict($nodes);
        if (!$file->declarationsOnly) {
            $this->register(new CallableSource('script:' . $file->path, $script, $file->path, strict: $strict));
        }
        (new Indexer($this))->scan($nodes, $file->path, $strict);
    }

    /**
     * Registers a declaration and reports duplicate names instead of choosing one.
     * @param CallableSource $source Captured callable
     */
    public function register(CallableSource $source): void
    {
        $this->symbolNames = null;
        $key = (new CallableIdentity())->key($source->symbol);
        if (isset($this->declarations[$key])) {
            if ($this->files[$source->path]->declarationsOnly && !$this->files[$this->declarations[$key]->path]->declarationsOnly) {
                return;
            }
            if ($source->node->getAttribute('deriverConditional') === true || $this->declarations[$key]->node->getAttribute('deriverConditional') === true) {
                $variant = $source->symbol . '@declaration:' . $source->path . ':' . $source->node->getStartFilePos();
                $this->conditionalDeclarations[$key][] = $variant;
                $declaration = new CallableSource($variant, $source->node, $source->path, $source->className, $source->strict);
                $this->declarations[(new CallableIdentity())->key($variant)] = $declaration;
                if ($this->indexReferences) {
                    $this->references->add($declaration);
                }
                return;
            }
            $this->issues[] = new Frontier('INVALID_PROGRAM', $this->builder($source->path)->source($source->node), 'duplicate:' . $source->symbol);
            return;
        }
        $this->declarations[$key] = $source;
        if ($this->indexReferences) {
            $this->references->add($source);
        }
    }

    /**
     * Creates a graph builder associated with captured source bytes.
     * @param string $path Captured source path
     * @return InstructionEmitter New graph builder
     */
    public function builder(string $path): InstructionEmitter
    {
        return new InstructionEmitter($this->snapshotId, $path, $this->files[$path]->contents, $this->lineMaps[$path]);
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
        return $this->graphs[$key] = (new Lowerer($this))->compile($source);
    }

    /**
     * Registers a lexical closure without executing its body.
     * @param Expr\Closure|Expr\ArrowFunction $node Closure expression
     * @param string $path \Deriver\Deriver\Sources\Source path
     * @param string $className Lexical class
     * @return string Stable closure identity
     */
    public function registerClosure(Expr\Closure|Expr\ArrowFunction $node, string $path, string $className): string
    {
        $symbol = 'closure:' . $path . ':' . $node->getStartFilePos() . ($className === '' ? '' : ':scope:' . $className);
        if (!isset($this->declarations[(new CallableIdentity())->key($symbol)])) {
            $this->register(new CallableSource($symbol, $node, $path, $className, $this->declarations['script:' . $path]->strict ?? false));
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
        if ($this->symbolNames !== null) {
            return $this->symbolNames;
        }
        $symbols = array_map(static fn (CallableSource $source): string => $source->symbol, array_values($this->declarations));
        sort($symbols);
        return $this->symbolNames = $symbols;
    }

    /**
     * Tests exact declaration spelling without allocating or scanning the symbol list.
     */
    public function hasSymbol(string $symbol): bool
    {
        return ($this->declarations[(new CallableIdentity())->key($symbol)]->symbol ?? null) === $symbol;
    }

    /**
     * @return list<Frontier> \Deriver\Deriver\Sources\Source diagnostics.
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
        return $this->graphs['constant:' . $key] ??= (new Lowerer($this))->expression($source->node, $source->path, 'constant:' . $symbol, $source->className);
    }
    /**
     * Identifies source owners without eagerly lowering unrelated functions.
     * @param string $symbol Function or method selector
     * @return list<string> Candidate callable identities
     */
    #[Override]
    public function callOwners(string $symbol): array
    {
        return $this->references->calls($symbol);
    }

    /**
     * Finds possible property writers without compiling unrelated callables.
     * @return list<string> \Deriver\Deriver\Sources\Source owner identities
     */
    public function propertyOwners(string $name): array
    {
        return $this->references->properties($name);
    }

    /**
     * @return list<string> Lexical owners of a captured closure
     */
    public function closureOwners(string $symbol): array
    {
        return $this->references->closures($symbol);
    }

    /**
     * @return list<string> Scripts which may assign a global
     */
    public function globalOwners(string $name): array
    {
        return $this->references->globals($name);
    }

    /**
     * Retains conditional implementations without registering or executing them.
     * @return list<string> Additional source declaration identities
     */
    public function variants(string $symbol): array
    {
        return $this->conditionalDeclarations[(new CallableIdentity())->key($symbol)] ?? [];
    }
}
