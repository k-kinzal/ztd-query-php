<?php

declare(strict_types=1);

namespace Deriver\Source\Cache;

use Deriver\ControlFlow\CallableGraph;
use Deriver\Source\Compilation\CallableCompiler;
use Deriver\Source\Declaration\CallableSource;
use Deriver\Source\Declaration\ProjectIndex;

/**
 * Reuses source-only lowering across snapshots; semantic results remain world-specific.
 * @visibility root
 */
final class GraphCache
{
    /**
     * @var array<string, GraphTemplate> Bounded immutable templates in recency order
     */
    public array $records = [];
    /**
     * Number of IR templates reused.
     */
    public int $hits = 0;
    /**
     * Number of source bodies lowered.
     */
    public int $misses = 0;
    /**
     * @var array<string, int> Conservative source and instruction retention estimates.
     */
    public array $weights = [];

    /**
     * Reuses a matching file, lexical declaration, target, and lowerer version.
     * @param ProjectIndex $index Receiving declaration world
     * @param CallableSource $source Demanded declaration
     * @return CallableGraph Graph with references owned by the receiving snapshot
     */
    public function read(ProjectIndex $index, CallableSource $source): CallableGraph
    {
        $key = hash('sha256', serialize(['lowerer-2', $index->files[$source->path]->declarationsOnly, $index->profile->id(), $index->fileHashes[$source->path], $source->path, $source->symbol, $source->className, $source->strict, $source->cacheSalt]));
        $template = $this->records[$key] ?? null;
        if ($template !== null) {
            $this->hits++;
            unset($this->records[$key]);
            foreach ($template->closures as $name => $closure) {
                $index->declarations[$name] ??= $closure;
            }
        } else {
            $this->misses++;
            $previous = $index->capturedClosures;
            $index->capturedClosures = [];
            $body = (new CallableCompiler($index))->compile($source);
            $template = new GraphTemplate($body, $index->capturedClosures ?? []);
            $index->capturedClosures = $previous;
        }
        $weight = strlen($index->files[$source->path]->contents) * 32;
        foreach ($template->body->blocks as $block) {
            $weight += (count($block->instructions) + 1) * 2048;
        }
        if ($weight <= 33554432) {
            $this->records[$key] = $template;
            $this->weights[$key] = $weight;
        }
        while (count($this->records) > 512 || array_sum($this->weights) > 33554432) {
            $oldest = array_key_first($this->records);
            unset($this->records[$oldest], $this->weights[$oldest]);
        }
        return (new SnapshotRebase($index->snapshotId))->callable($template->body);
    }
}
