<?php

declare(strict_types=1);

namespace Deriver\Deriver\Lowerer\Control;

use Deriver\Deriver\Lowerer\Graph\Literal;
use Deriver\Deriver\Lowerer\Graph\Terminator;
use Deriver\Deriver\Lowerer\LoweringScope;
use PhpParser\Node\StaticVar;

/**
 * Keeps dynamic function-static initializers behind their one-time storage test.
 * @visibility Deriver\Deriver\Lowerer\Declaration
 * @visibility Deriver\Deriver\Lowerer
 * @visibility Deriver\Deriver\Sources
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
        $g->end(new Terminator(\Deriver\Deriver\Lowerer\Graph\TerminatorKind::Branch, $present, [$join, $initialize]));
        $g->current = $initialize;
        $initial = $variable->default === null ? $g->emit($variable, 'constant', constant: Literal::constant(null)) : $l->expression($variable->default);
        $g->emit($variable, 'static-local', [$address, $initial], $l->symbol);
        $g->jump($join);
        $g->current = $join;
    }
}
