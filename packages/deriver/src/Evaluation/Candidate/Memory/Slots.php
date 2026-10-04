<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Candidate\Memory;

use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Candidate\Calls;
use Deriver\Evaluation\Candidate\Choices;
use Deriver\Evaluation\Candidate\Derivation;
use Deriver\Evaluation\Candidate\Frame;
use Deriver\Evaluation\Candidate\Graph;
use Deriver\Evaluation\Candidate\Guards;
use Deriver\Evaluation\Candidate\Models;
use Deriver\Value\Identity;
use Deriver\Value\Term;

/**
 * Expands only writes to a demanded model slot on the corresponding receiver.
 * @visibility root
 */
final class Slots
{
    /**
     * Uses the same dependency evaluator as ordinary source values.
     */
    public function __construct(private readonly Derivation $engine)
    {
    }

    /**
     * Continues a model slot input at the calling source position.
     */
    public function read(Frame $frame, Instruction $address, int $depth): Term
    {
        $receiver = $this->engine->value($frame, $address->operands[0], $depth);
        if ($frame->origin !== null) {
            [$block, $offset] = $frame->origin->frame->graph->positions[$frame->origin->register];
            return $this->before($frame->origin->frame, $receiver, $address->name, $block, $offset, $depth);
        }
        return $this->initial($frame, $receiver, $address->name);
    }

    /**
     * Searches reaching model writes without deriving other receiver state.
     * @param array<int, true> $seen Visited predecessor blocks
     */
    public function before(Frame $frame, Term $receiver, string $slot, int $block, int $offset, int $depth, array $seen = []): Term
    {
        if (isset($seen[$block]) || $this->engine->context->boundary($depth) !== null) {
            return new Term('state-read', $slot, [$receiver], ['reason' => 'DEFERRED_STATE', 'type' => $this->engine->context->models->state->slots[$slot]->type ?? 'mixed']);
        }
        $seen[$block] = true;
        $instructions = $frame->graph->body->blocks[$block]->instructions;
        for ($i = $offset - 1; $i >= 0; $i--) {
            $call = $instructions[$i];
            if ($call->operation !== 'invoke-method') {
                continue;
            }
            $actual = $this->engine->value($frame, $call->operands[0], $depth);
            if ((new Identity())->key($actual) !== (new Identity())->key($receiver)) {
                continue;
            }
            $value = $this->effect($frame, $call, $slot, $depth);
            if ($value !== null) {
                return (new Guards($this->engine))->at($frame, $block, $value, $depth);
            }
        }
        $parents = $frame->graph->predecessors[$block] ?? [];
        if ($parents === []) {
            return $this->initial($frame, $receiver, $slot);
        }
        $alternatives = [];
        foreach ($parents as $parent) {
            $value = $this->before($frame, $receiver, $slot, $parent, count($frame->graph->body->blocks[$parent]->instructions), $depth, $seen);
            $alternatives[] = [(new Guards($this->engine))->edge($frame, $parent, $block, $value, $depth), []];
        }
        return (new Choices())->make($alternatives);
    }

    /**
     * Demands a selected model's final write to one slot.
     */
    public function effect(Frame $frame, Instruction $call, string $slot, int $depth): ?Term
    {
        $target = $this->engine->context->index->target($frame->graph, $call);
        $model = (new Models($this->engine))->graph($frame, $call, $target, $depth);
        if (!$model instanceof Graph) {
            return $model;
        }
        foreach ($model->definitions as $write) {
            $address = $model->definitions[$write->operands[0] ?? ''] ?? null;
            if ($write->operation === 'write' && $address?->operation === 'model-state-address' && $address->name === $slot) {
                $bound = (new Calls($this->engine))->bind($frame, $call, $model);
                return (new Calls($this->engine))->finalStorage($bound, $address->result, $depth - 1);
            }
        }
        return null;
    }

    /**
     * Keeps allocated defaults distinct from external receiver state.
     */
    public function initial(Frame $frame, Term $receiver, string $slot): Term
    {
        $declaration = $this->engine->context->models->state->slots[$slot] ?? null;
        if ($receiver->kind === 'object' && $declaration?->initial !== null) {
            return $declaration->initial;
        }
        $reference = $this->engine->context->reference($frame, 'state:' . $slot, $frame->graph->body->source, $declaration->type ?? 'mixed', 'EXTERNAL_STATE');
        return new Term('state-read', $slot, [$receiver, $reference], ['type' => $declaration->type ?? 'mixed']);
    }
}
