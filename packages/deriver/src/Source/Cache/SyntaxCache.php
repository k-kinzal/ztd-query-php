<?php

declare(strict_types=1);

namespace Deriver\Source\Cache;

use Deriver\Exception\InvalidInputException;
use Deriver\Project\ProjectInput;
use Deriver\Project\SourceFile;
use Deriver\Project\SourceLimits;
use Deriver\Project\TargetProfile;
use Deriver\Source\MagicContext;
use Deriver\Source\SyntaxSize;
use Deriver\Source\Validation\AssignmentPatterns;
use Deriver\Source\Validation\ClassScope;
use Deriver\Source\Validation\TargetSyntax;
use PhpParser\ErrorHandler\Collecting;
use PhpParser\Node\Stmt;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PhpParser\PhpVersion;

/**
 * Bounded in-memory syntax reuse, tied to the loaded parser and grammar profile.
 * @visibility root
 */
final class SyntaxCache
{
    /**
     * @var array<string, SyntaxTree> Most recently used source trees
     */
    public array $records = [];
    /**
     * Number of source hashes reused by this cache.
     */
    public int $hits = 0;
    /**
     * Number of source hashes parsed by this cache.
     */
    public int $misses = 0;
    /**
     * @var array<string, int> Conservative retention estimates for cached syntax.
     */
    public array $weights = [];

    /**
     * Parses captured bytes or clones a matching resolved tree without loading source code.
     * @param SourceFile $file Captured bytes; paths do not affect PHP grammar
     * @param TargetProfile $profile Grammar version
     * @param SourceLimits $limits Source admission policy
     * @return SyntaxTree Independent parser nodes and relative diagnostics
     * @throws InvalidInputException If parser output violates its top-level contract
     */
    public function read(SourceFile $file, TargetProfile $profile, SourceLimits $limits = new SourceLimits()): SyntaxTree
    {
        $limits->check(new ProjectInput([$file]));
        $key = hash('sha256', 'syntax-5:' . $profile->id() . ':' . $file->contents);
        $tree = $this->records[$key] ?? null;
        if ($tree === null) {
            $this->misses++;
            $tree = $this->parse($file->contents, $profile, $limits);
        } else {
            $this->hits++;
            unset($this->records[$key]);
        }
        if ($tree->size > $limits->nodes || $tree->depth > $limits->depth) {
            throw new InvalidInputException('SOURCE_LIMIT: cached syntax exceeds the admission limit.');
        }
        $weight = strlen($file->contents) * 32 + $tree->size * 1024;
        if ($weight <= 33554432) {
            $this->records[$key] = $tree;
            $this->weights[$key] = $weight;
            while (count($this->records) > 128 || array_sum($this->weights) > 33554432) {
                $oldest = array_key_first($this->records);
                unset($this->records[$oldest], $this->weights[$oldest]);
            }
        }
        return $tree->copy();
    }

    /**
     * Runs the loaded parser and name resolver, capturing all errors as source diagnostics.
     * @param string $contents PHP source bytes
     * @param TargetProfile $profile Grammar profile
     * @param SourceLimits $limits Source admission policy
     * @return SyntaxTree Resolved parse result
     * @throws InvalidInputException If parser output violates its top-level contract
     */
    public function parse(string $contents, TargetProfile $profile, SourceLimits $limits = new SourceLimits()): SyntaxTree
    {
        if (strlen($contents) > $limits->fileBytes) {
            throw new InvalidInputException('SOURCE_LIMIT: parser input exceeds the admission limit.');
        }
        $errors = new Collecting();
        $parser = (new ParserFactory())->createForVersion(PhpVersion::fromString($profile->version));
        $parsed = $parser->parse($contents, $errors) ?? [];
        [$size, $depth] = (new SyntaxSize())->measure(array_values($parsed), $limits);
        $nodes = [];
        if (!$errors->hasErrors()) {
            foreach ((new NodeTraverser(new TargetSyntax($profile, $errors, array_values($parser->getTokens())), new ClassScope($errors), new AssignmentPatterns($errors), new NameResolver($errors), new MagicContext()))->traverse($parsed) as $node) {
                if (!$node instanceof Stmt) {
                    throw new InvalidInputException('The parser returned an invalid top-level node.');
                }
                $nodes[] = $node;
            }
        }
        $diagnostics = [];
        foreach ($errors->getErrors() as $error) {
            $diagnostics[] = ['message' => $error->getRawMessage(), 'line' => max(1, $error->getStartLine())];
        }
        return new SyntaxTree($errors->hasErrors() ? [] : $nodes, $diagnostics, $size, $depth);
    }
}
