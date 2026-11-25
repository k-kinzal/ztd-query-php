<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Ordering;

use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\IntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\Literal\ProjectedIntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\Rows\LocalProjection;
use SqlSemantics\Platform\MySql\Ir\Rows\With\WithProjection;
use SqlSemantics\Platform\MySql\Ir\Rows\With\WithRead;
use SqlSemantics\Platform\MySql\Ir\Select;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;

/**
 * Checks a child whose consumer replaces its result aliases with local names.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class LocalProjectionAliases
{
    /**
     * Checks inherited native names before publishing a positional remapping.
     */
    public function check(Select|LocalProjection|WithProjection|WithRead $child): void
    {
        $output = $child instanceof WithProjection ? $child->body : $child;
        if (!$output instanceof Select || !$this->requiresRequested($output)) {
            return;
        }
        $seen = [];
        foreach ($output->columns as $column) {
            $name = $column->name;
            $operand = $column->expression;
            Check::input(
                ($operand instanceof IntegerLiteral || $operand instanceof ProjectedIntegerLiteral)
                && ($name instanceof Identifier || $name instanceof RequestedResultAlias),
                'Inherited requested aliases need exact signed literal fields.'
            );
            $requested = $name instanceof RequestedResultAlias ? $name->requested : $name->value;
            Check::input(preg_match('/\A[\x01-\x7f]*\z/', $requested) === 1, 'Inherited requested aliases require ASCII names.');
            $key = $name->key();
            Check::input(!isset($seen[$key]), 'Inherited requested aliases must resolve to distinct names.');
            $seen[$key] = true;
        }
    }

    /**
     * Checks whether a derived table can declare separate positional column names.
     */
    public function checkDerived(Select|LocalProjection|WithProjection $child): void
    {
        if ($child instanceof Select && $this->requiresRequested($child)) {
            Check::input(
                $child->release !== GrammarRelease::MySql5651 && $child->release !== GrammarRelease::MySql5744,
                'A derived table with requested aliases requires MySQL 8.0 or later.'
            );
        }
    }

    /**
     * Identifies a result whose native alias must survive local positional naming.
     */
    public function requiresRequested(Select|LocalProjection|WithProjection|WithRead $child): bool
    {
        $output = $child instanceof WithProjection ? $child->body : $child;
        if (!$output instanceof Select) {
            return false;
        }
        foreach ($output->columns as $column) {
            if ($column->name instanceof RequestedResultAlias) {
                return true;
            }
        }

        return false;
    }
}
