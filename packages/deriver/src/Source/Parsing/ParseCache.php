<?php

declare(strict_types=1);

namespace Deriver\Source\Parsing;

use Deriver\Exception\InvalidInputException;
use Deriver\Project\ProjectInput;
use Deriver\Project\SourceFile;
use Deriver\Project\SourceLimits;
use Deriver\Project\TargetProfile;

/**
 * Bounded in-memory reuse of parsed files, keyed by bytes and grammar profile.
 * The cache holds no parsing logic: every miss is delegated to the parser, every hit is a copy of its result.
 * @visibility root
 */
final class ParseCache
{
    /**
     * @var array<string, ParsedFile> Most recently used source trees
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
     * Captures the parser every miss is delegated to.
     */
    public function __construct(public readonly SourceParser $parser = new SourceParser())
    {
    }

    /**
     * Parses captured bytes or clones a matching resolved tree without loading source code.
     * @param SourceFile $file Captured bytes; paths do not affect PHP grammar
     * @param TargetProfile $profile Grammar version
     * @param SourceLimits $limits Source admission policy
     * @return ParsedFile Independent parser nodes and relative diagnostics
     * @throws InvalidInputException If parser output violates its top-level contract
     */
    public function read(SourceFile $file, TargetProfile $profile, SourceLimits $limits = new SourceLimits()): ParsedFile
    {
        $limits->check(new ProjectInput([$file]));
        $key = hash('sha256', 'syntax-5:' . $profile->id() . ':' . $file->contents);
        $tree = $this->records[$key] ?? null;
        if ($tree === null) {
            $this->misses++;
            $tree = $this->parser->parse($file->contents, $profile, $limits);
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
}
