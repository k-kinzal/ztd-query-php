<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Memory;

use Deriver\Deriver\Evaluator\Expression\Storage;
use Deriver\Deriver\Evaluator\Scope\Frame;

/**
 * Indexes local reference-cell identities independently of their values.
 * @visibility parent
 */
final class Aliases
{
    /**
     * Resolves the cell used at a particular write position.
     */
    public function key(Storage $storage, Frame $frame, string $address, int $block, int $offset): string
    {
        $name = $storage->key($frame, $address);
        if (!$frame->graph->hasAliases) {
            return $name;
        }
        return $this->map($storage, $frame, $block, $offset)[$name] ?? $name;
    }

    /**
     * Collects alias bindings without requesting any stored value.
     * @param array<int, true> $seen Visited predecessor blocks
     * @return array<string, string> \Deriver\Deriver\Evaluator\Expression\Storage names mapped to reference-cell identities
     */
    public function map(Storage $storage, Frame $frame, int $block, int $offset, array $seen = []): array
    {
        $key = $frame->identity . ':' . $block . ':' . $offset;
        if (isset($storage->engine->context->aliases[$key])) {
            return $storage->engine->context->aliases[$key];
        }
        if (isset($seen[$block])) {
            return [];
        }
        $seen[$block] = true;
        $maps = [];
        foreach ($frame->graph->predecessors[$block] ?? [] as $parent) {
            $maps[] = $this->map($storage, $frame, $parent, count($frame->graph->body->blocks[$parent]->instructions), $seen);
        }
        $result = $this->merge($maps);
        foreach (array_slice($frame->graph->body->blocks[$block]->instructions, 0, $offset) as $instruction) {
            if ($instruction->operation === 'alias') {
                $left = $storage->key($frame, $instruction->operands[0]);
                $returned = $this->returned($storage, $frame, $instruction->operands[1]);
                $right = $storage->key($frame, $returned ?? $instruction->operands[1]);
                $result[$left] = $result[$right] ?? $right;
            } elseif ($instruction->operation === 'unset') {
                $name = $storage->key($frame, $instruction->operands[0]);
                $result[$name] = $name . ':unset:' . $instruction->id;
            }
        }
        return $storage->engine->context->aliases[$key] = $result;
    }

    /**
     * Maps a reference-returning parameter back to its caller's storage cell.
     */
    public function returned(Storage $storage, Frame $frame, string $address): ?string
    {
        $definition = $frame->graph->definitions[$address] ?? null;
        $call = $definition?->operation === 'returned-address' ? $frame->graph->definitions[$definition->operands[0]] : null;
        if ($call === null) {
            return null;
        }
        $index = $storage->engine->context->index;
        $body = $index->graph($index->target($frame->graph, $call));
        if ($body === null || !$body->body->byReference || $body->hasAliases) {
            return null;
        }
        $actuals = [];
        foreach ($body->returns() as [$block, $register]) {
            $reference = $body->definitions[$register] ?? null;
            $local = $reference?->operation === 'reference' ? $body->definitions[$reference->operands[0]] : null;
            if ($local?->operation !== 'local') {
                return null;
            }
            $position = array_search($local->name, array_column($body->body->parameters, 'name'), true);
            if ($position === false || !$body->body->parameters[$position]->byReference) {
                return null;
            }
            $argument = (new \Deriver\Deriver\Evaluator\CallEvaluation($storage->engine))->actual($call, $local->name, $position);
            $wrapper = $frame->graph->definitions[$argument ?? ''] ?? null;
            if ($wrapper?->operation !== 'argument' || ($wrapper->attributes['address'] ?? false) !== true) {
                return null;
            }
            $actuals[] = $wrapper->operands[1];
        }
        return count(array_unique($actuals)) === 1 ? $actuals[0] : null;
    }

    /**
     * Keeps disagreements explicit instead of merging distinct reference cells.
     * @param list<array<string, string>> $maps Incoming alias maps
     * @return array<string, string> Common or unresolved cell identities
     */
    public function merge(array $maps): array
    {
        $result = [];
        foreach ($maps as $map) {
            foreach ($map as $name => $cell) {
                $result[$name] = $cell;
            }
        }
        foreach ($result as $name => $cell) {
            foreach ($maps as $map) {
                if (($map[$name] ?? $name) !== $cell) {
                    $result[$name] = 'unresolved-alias:' . $name;
                }
            }
        }
        return $result;
    }
}
