<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Candidate\Memory;

use Deriver\Evaluation\Candidate\Frame;
use Deriver\Evaluation\Candidate\Storage;

/**
 * Indexes local reference-cell identities independently of their values.
 * @visibility root
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
     * @return array<string, string> Storage names mapped to reference-cell identities
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
                $right = $storage->key($frame, $instruction->operands[1]);
                $result[$left] = $result[$right] ?? $right;
            } elseif ($instruction->operation === 'unset') {
                $name = $storage->key($frame, $instruction->operands[0]);
                $result[$name] = $name . ':unset:' . $instruction->id;
            }
        }
        return $storage->engine->context->aliases[$key] = $result;
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
