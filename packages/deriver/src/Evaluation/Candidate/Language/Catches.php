<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Candidate\Language;

use Deriver\Evaluation\Call\Dispatch;
use Deriver\Evaluation\Candidate\Choices;
use Deriver\Evaluation\Candidate\Derivation;
use Deriver\Evaluation\Candidate\Frame;
use Deriver\Evaluation\Candidate\Guards;
use Deriver\Value\Term;

/**
 * Resolves catch values selected by exceptions in a demanded return expression.
 * @visibility root
 */
final class Catches
{
    /**
     * Routes a known expression failure to its lexical catch definition.
     */
    public function resolve(Derivation $engine, Frame $frame, int $block, Term $value, int $depth): Term
    {
        if (!in_array($value->kind, ['throwable', 'choice'], true)) {
            return $value;
        }
        return (new Choices())->apply('catch', [$value], function (array $values) use ($engine, $frame, $block, $depth): Term {
            $error = $values[0];
            if ($error->kind !== 'throwable') {
                return $error;
            }
            foreach ($this->regions($frame, $block) as $region) {
                foreach ($frame->graph->body->regions[$region]->catches as $catch) {
                    foreach ($catch->types as $type) {
                        if ($type !== 'Throwable' && !(new Dispatch($engine->context->index->program))->subtype((string) $error->literal, $type)) {
                            continue;
                        }
                        $bindings = $frame->bindings;
                        $bindings[$catch->variable] = $error;
                        $bound = new Frame($frame->graph, $frame->identity . ':catch:' . $catch->block, $bindings, $frame->properties, $frame->calls, $frame->invocation, origin: $frame->origin);
                        $alternatives = [];
                        foreach ($frame->graph->returns($catch->block) as [$destination, $register]) {
                            $alternatives[] = [(new Guards($engine))->at($bound, $destination, $engine->value($bound, $register, $depth), $depth), []];
                        }
                        return (new Choices())->make($alternatives);
                    }
                }
            }
            return $error;
        }, $engine->context->budget->partitions);
    }

    /**
     * Looks up lexical handlers along the requested block's predecessor chain.
     * @param array<int, true> $seen Visited predecessor blocks
     * @return list<int> Candidate lexical handler regions
     */
    public function regions(Frame $frame, int $block, array $seen = []): array
    {
        if (isset($seen[$block])) {
            return [];
        }
        $seen[$block] = true;
        $regions = [];
        foreach (array_reverse($frame->graph->body->blocks[$block]->instructions) as $instruction) {
            if ($instruction->operation === 'enter-try') {
                $regions[] = (int) $instruction->attributes['region'];
            }
        }
        foreach ($frame->graph->predecessors[$block] ?? [] as $parent) {
            array_push($regions, ...$this->regions($frame, $parent, $seen));
        }
        return array_values(array_unique($regions));
    }
}
