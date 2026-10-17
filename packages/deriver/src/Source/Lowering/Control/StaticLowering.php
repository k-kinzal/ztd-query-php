<?php

declare(strict_types=1);

namespace Deriver\Source\Lowering\Control;

use Deriver\ControlFlow\Terminator;
use Deriver\Source\Lowering\LoweringScope;
use Deriver\Value\Term;
use PhpParser\Node\StaticVar;

/**
 * Keeps dynamic function-static initializers behind their one-time storage test.
 * @visibility Deriver\Source
 */
final class StaticLowering
{
    /**
     * @param LoweringScope $lowering Current callable compiler
     */
    public function __construct(public readonly LoweringScope $lowering)
    {
    }

    /**
     * Binds existing storage or evaluates the initializer on its first execution.
     * @param StaticVar $variable One function-static declaration
     */
    public function lower(StaticVar $variable): void
    {
        $l = $this->lowering;
        $g = $l->graph;
        $address = $l->location($variable->var);
        $present = $g->emit($variable, 'static-initialized', [$address], $l->symbol);
        $initialize = $g->block();
        $join = $g->block();
        $g->end(new Terminator('branch', $present, [$join, $initialize]));
        $g->current = $initialize;
        $initial = $variable->default === null ? $g->emit($variable, 'constant', constant: Term::constant(null)) : $l->expression($variable->default);
        $g->emit($variable, 'static-local', [$address, $initial], $l->symbol);
        $g->jump($join);
        $g->current = $join;
    }
}
