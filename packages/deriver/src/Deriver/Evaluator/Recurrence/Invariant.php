<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Recurrence;

use Deriver\Deriver\Evaluator\Evaluator;
use Deriver\Deriver\Evaluator\Expression\Storage;
use Deriver\Deriver\Evaluator\Index\BodyIndex;
use Deriver\Deriver\Evaluator\Scope\Frame;

/**
 * Separates demanded storage from loops which cannot update it.
 * @visibility parent
 */
final class Invariant
{
    /**
     * Proves that the requested storage is unchanged by the selected loop.
     */
    public function check(Evaluator $engine, Frame $frame, string $address, int $header): bool
    {
        $storage = new Storage($engine);
        $wanted = $storage->key($frame, $address);
        $end = $frame->graph->body->blocks[$header]->terminator;
        $exit = $end->targets[1] ?? -1;
        $pending = [$header];
        $seen = [];
        while ($pending !== []) {
            $block = array_pop($pending);
            if (isset($seen[$block]) || $block === $exit) {
                continue;
            }
            $seen[$block] = true;
            foreach ($frame->graph->body->blocks[$block]->instructions as $write) {
                if ($write->operation === 'alias') {
                    return false;
                }
                if (BodyIndex::writes($write)) {
                    $root = $frame->graph->root($write);
                    if ($root !== null && ($storage->key($frame, $root->result) === $wanted || $root->operation === 'dynamic-local')) {
                        return false;
                    }
                }
                if (in_array($write->operation, ['invoke', 'invoke-method', 'invoke-static'], true) && ((new \Deriver\Deriver\Evaluator\Calls($engine))->passed($frame, $write, $address) !== null || in_array($frame->graph->definitions[$address]->operation ?? '', ['field-address', 'static-address'], true) || (new \Deriver\Deriver\Evaluator\Memory\Globals())->declares($frame, $frame->graph->definitions[$address]->name ?? ''))) {
                    return false;
                }
            }
            array_push($pending, ...($frame->graph->successors[$block] ?? []));
        }
        return true;
    }
}
