<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Dml;

use SqlParser\Parser\Node;
use SqlSemantics\Construction\RejectedInput;
use SqlSemantics\Construction\SourceRanges;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Dml\Problem\EvaluationWildcard;
use SqlSemantics\Platform\MySql\Statement\Name\TableWildcard;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;

/**
 * Reads DO items without publishing a wildcard as an executable operand.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering
 */
final class EvaluationItems
{
    /**
     * @param Lowering $lowering The input lowering context for the selected grammar
     */
    public function __construct(public readonly Lowering $lowering)
    {
    }

    /**
     * @return list<SelectExpression> The expression items
     * @throws RejectedInput When DO requests a wildcard
     */
    public function read(Node $node): array
    {
        $items = $this->lowering->queries->selectItems($node);
        $expressions = [];
        $wildcard = null;
        foreach ($items as $item) {
            if ($item instanceof SelectExpression) {
                $expressions[] = $item;
            } else {
                $wildcard ??= $item;
            }
        }
        if ($wildcard !== null) {
            $end = (new SourceRanges())->span($node)[1] ?? 0;
            throw new RejectedInput(new EvaluationWildcard($wildcard instanceof TableWildcard ? $wildcard->table : null, $this->requiresDatabase($expressions)), $end, false);
        }

        return $expressions;
    }

    /**
     * Reads a DO program member, retaining deferred wildcard expansion when present.
     */
    public function program(Node $node): \SqlSemantics\Platform\MySql\Statement\Dml\Evaluation|\SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredEvaluation|null
    {
        $current = $node;
        while (!in_array($current->name, ['do', 'do_stmt'], true)) {
            $children = array_values(array_filter($current->children, static fn ($child): bool => $child instanceof Node));
            if (count($children) !== 1) {
                return null;
            }
            $current = $children[0];
        }
        $form = $this->lowering->form($current);
        if ($form->signature === 'do: DO_SYM expr_list') {
            $items = array_map(static fn ($value) => $value instanceof \SqlSemantics\Statement\Scalar ? new SelectExpression($value) : new \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredProjection($value), (new \SqlSemantics\Platform\MySql\Lowering\Expression\ListRule($this->lowering))->operands($form->node(1)));
            return $this->stored($items);
        }
        $list = $form->signature === 'do_stmt: DO_SYM empty_select_options select_item_list' ? $form->node(2) : $form->node(1);
        $items = (new \SqlSemantics\Platform\MySql\Lowering\Query\Shared\StoredSelections($this->lowering))->items($list);
        return $this->stored($items);
    }

    /**
     * @param list<SelectExpression|\SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredProjection|\SqlSemantics\Platform\MySql\Statement\Query\Star|TableWildcard> $items Complete DO items
     */
    public function stored(array $items): \SqlSemantics\Platform\MySql\Statement\Dml\Evaluation|\SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredEvaluation
    {
        $wildcards = array_filter($items, static fn ($item): bool => !$item instanceof SelectExpression);

        return $wildcards === [] ? new \SqlSemantics\Platform\MySql\Statement\Dml\Evaluation(array_values(array_filter($items, static fn ($item): bool => $item instanceof SelectExpression))) : new \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredEvaluation($items);
    }

    /**
     * Identifies an unqualified stored call whose name needs a default database.
     *
     * @param list<SelectExpression> $items The already lowered expression items
     */
    public function requiresDatabase(array $items): bool
    {
        $pending = $items;
        while ($pending !== []) {
            $value = array_pop($pending);
            if (is_array($value)) {
                array_push($pending, ...array_values($value));
            } elseif ($value instanceof \SqlSemantics\Statement\Node) {
                if ($value instanceof FunctionCall && $value->schema === null && !$value->native($this->lowering->profile->grammar)) {
                    return true;
                }
                array_push($pending, ...array_values(get_object_vars($value)));
            }
        }

        return false;
    }
}
