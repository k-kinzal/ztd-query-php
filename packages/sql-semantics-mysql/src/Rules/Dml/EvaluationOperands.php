<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Dml;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Query\From\JoinedInput;
use SqlSemantics\Platform\MySql\Rules\Query\Projection;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\EvaluationWildcard;
use SqlSemantics\Platform\MySql\Statement\Name\TableWildcard;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Star;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredProjection;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Shape\RowShape;

/**
 * The grammatical operands and preparation conditions shared by immediate and stored DO.
 *
 * Wildcards are retained requests, although DO has no input table to expand.
 * Expansion precedes scalar preparation; individual operand dependencies retain
 * their order. The executor resolves those dependencies before the fixed condition.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class EvaluationOperands
{
    /**
     * Checks the grammatical list independently of whether it can execute.
     *
     * @param list<SelectExpression|DeferredProjection|Star|TableWildcard> $items The written items
     * @return non-empty-list<SelectExpression|DeferredProjection|Star|TableWildcard>
     */
    public function check(array $items): array
    {
        $list = [];
        foreach (Check::listOf($items, Node::class, 'DO has at least one item.', 1) as $position => $item) {
            Check::input($item instanceof SelectExpression || $item instanceof DeferredProjection || $item instanceof TableWildcard || ($item instanceof Star && $position === 0), 'A DO item is an expression or a qualified star, and an unqualified star comes first.');
            $list[] = $item;
        }
        return $list;
    }

    /**
     * Derives every operand in its statement or enclosing program environment.
     *
     * @param non-empty-list<SelectExpression|DeferredProjection|Star|TableWildcard> $items The retained items
     */
    public function derive(array $items, Derivation $derivation, Environment $environment): void
    {
        foreach ($items as $item) {
            if ($item instanceof DeferredProjection) {
                $derivation->scalar($item->expression, $environment);
            } else {
                (new Projection())->items([$item], $derivation, $environment, new JoinedInput(new RelationFact(new RowShape([])), [], []));
            }
        }
    }

    /**
     * Answers a fixed preparation condition without evaluating an operand.
     *
     * @param non-empty-list<SelectExpression|DeferredProjection|Star|TableWildcard> $items The retained items
     */
    public function problem(array $items): ?Diagnostic
    {
        foreach ($items as $item) {
            if ($item instanceof Star || $item instanceof TableWildcard) {
                return new EvaluationWildcard($item instanceof TableWildcard ? $item->table : null);
            }
        }
        foreach ($items as $item) {
            if ($item instanceof DeferredProjection) {
                return (new \SqlSemantics\Platform\MySql\Rules\Expression\Preparation\DeferredArguments())->problem([$item->expression]);
            }
        }
        return null;
    }
}
