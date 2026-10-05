<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Candidate\Language;

use Deriver\ControlFlow\ExceptionRegion;
use Deriver\Evaluation\Candidate\Choices;
use Deriver\Evaluation\Candidate\Derivation;
use Deriver\Evaluation\Candidate\Frame;
use Deriver\Evaluation\Candidate\Guards;
use Deriver\Value\Term;

/**
 * Treats finally completion as a dependency of the previously selected return value.
 * @visibility root
 */
final class Finalizers
{
    /**
     * Applies enclosing finalizers from the innermost region outward.
     */
    public function apply(Derivation $engine, Frame $frame, int $block, Term $value, int $depth): Term
    {
        foreach (array_reverse($frame->graph->body->regions) as $region) {
            if ($region->finally === null || !in_array($block, [...$region->protectedBlocks, ...$region->catchBlocks], true)) {
                continue;
            }
            $values = [];
            foreach ($this->completions($frame, $region) as $destination) {
                if (($reason = $engine->context->work()) !== null) {
                    return new Term('finally', operands: [$value], attributes: ['reason' => $reason]);
                }
                $end = $frame->graph->body->blocks[$destination]->terminator;
                $replacement = $value;
                if (in_array($end->kind, ['return', 'throw'], true)) {
                    $replacement = $engine->value($frame, $end->operand, $depth);
                    if ($end->kind === 'throw') {
                        $replacement = new Term('throwable', (string) ($replacement->attributes['type'] ?? 'Throwable'), [$replacement]);
                    }
                    $replacement = $this->apply($engine, $frame, $destination, $replacement, $depth);
                }
                $values[] = [(new Guards($engine))->at($frame, $destination, $replacement, $depth), []];
            }
            $value = (new Choices())->make($values);
        }
        return $value;
    }

    /**
     * Collects completion definitions confined to one finalizer's lexical region.
     * @return list<int> Return, throw, or resumption blocks
     */
    public function completions(Frame $frame, ExceptionRegion $region): array
    {
        $pending = $region->finally === null ? [] : [$region->finally];
        $seen = [];
        $completions = [];
        while ($pending !== []) {
            $id = array_pop($pending);
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $block = $frame->graph->body->blocks[$id];
            $next = array_values(array_filter($frame->graph->successors[$id] ?? [], static fn (int $target): bool => in_array($target, $region->finallyBlocks, true)));
            if (in_array($block->terminator->kind, ['return', 'throw'], true) || $next === []) {
                $completions[] = $id;
            } else {
                array_push($pending, ...$next);
            }
        }
        sort($completions);
        return $completions;
    }
}
