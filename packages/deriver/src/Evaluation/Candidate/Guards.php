<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Candidate;

use Deriver\Value\Operations;
use Deriver\Value\Term;

/**
 * Resolves only conditions selecting a demanded definition, retaining branch identity.
 * @visibility root
 */
final class Guards
{
    /**
     * Captures the dependencies used by this component.
     */
    public function __construct(public readonly Derivation $engine)
    {
    }

    /**

     * @param array<int, true> $seen

     */
    public function at(Frame $frame, int $block, Term $value, int $depth, array $seen = []): Term
    {
        if ($block === 0 || isset($seen[$block])) {
            return $value;
        }
        $seen[$block] = true;
        $frame->graph->controls ??= (new Control\Dependencies())->build($frame->graph, $this->engine->context);
        if ($frame->graph->controls === null) {
            return new Term('controlled-value', operands: [$value, $this->engine->context->reference($frame, 'control:' . $block, $frame->graph->body->source, reason: $this->engine->context->stopReason ?? 'BUDGET_EXCEEDED')], attributes: ['reason' => $this->engine->context->stopReason]);
        }
        foreach ($frame->graph->controls[$block] ?? [] as [$parent, $child]) {
            $source = (new Recurrence\Definitions($this->engine))->predecessor($frame, $parent, $child);
            $value = $this->edge($source, $parent, $child, $value, $depth);
            $value = $this->at($source, $parent, $value, $depth, $seen);
        }
        return $value;
    }

    /**
     * Attaches the decision represented by one predecessor edge.
     */
    public function edge(Frame $frame, int $parent, int $child, Term $value, int $depth): Term
    {
        $end = $frame->graph->body->blocks[$parent]->terminator;
        if ($end->kind !== 'branch') {
            return $value;
        }
        $expected = $end->targets[0] === $child;
        $condition = $this->engine->value($frame, $end->operand, $depth);
        $alternatives = [];
        foreach ((new Choices())->alternatives($condition) as [$test, $guard]) {
            $truth = (new Operations())->truth($test);
            if ($truth !== null && $truth !== $expected) {
                continue;
            }
            $guard[$frame->identity . ':condition:' . $end->operand] = $expected;
            $selected = Evidence\Provenance::operation($value, [$test], 'selection');
            $selected = Evidence\Provenance::wrap($selected, 'choice', $frame->graph->definitions[$end->operand]->source ?? $frame->graph->body->source, ['selector' => $frame->identity . ':condition:' . $end->operand, 'branch' => $expected]);
            $alternatives[] = [$selected, $guard];
        }
        return (new Choices())->make($alternatives);
    }
}
