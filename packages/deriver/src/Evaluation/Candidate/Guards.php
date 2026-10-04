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
        $parents = (new Recurrence\Definitions($this->engine))->parents($frame, $block);
        if ($parents === []) {
            return $value;
        }
        $alternatives = [];
        foreach ($parents as $parent) {
            $source = (new Recurrence\Definitions($this->engine))->predecessor($frame, $parent, $block);
            $candidate = $this->edge($source, $parent, $block, $value, $depth);
            $alternatives[] = [$this->at($source, $parent, $candidate, $depth, $seen), []];
        }
        return (new Choices())->make($alternatives);
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
            $alternatives[] = [$value, $guard];
        }
        return (new Choices())->make($alternatives);
    }
}
