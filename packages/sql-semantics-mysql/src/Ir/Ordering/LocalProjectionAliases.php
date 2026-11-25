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
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\NativeLocalNames;

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
        $names = new NativeLocalNames();
        foreach ($output->columns as $column) {
            $name = $column->name;
            $operand = $column->expression;
            Check::input(
                ($operand instanceof IntegerLiteral || $operand instanceof ProjectedIntegerLiteral)
                && ($name instanceof Identifier || $name instanceof RequestedResultAlias),
                'Inherited requested aliases need exact signed literal fields.'
            );
            $prefix = $name instanceof RequestedResultAlias ? $names->prefix($name->requested) : $name->value;
            $key = $names->key($prefix);
            Check::input(!isset($seen[$key]), 'Inherited requested aliases must resolve to distinct names.');
            $seen[$key] = true;
        }
    }

    /**
     * Closes the chosen local name layout on the child's release.
     */
    public function checkDerived(Select|LocalProjection|WithProjection $child, bool $inherited = false): void
    {
        $needsInherited = $this->needsInherited($child);
        Check::input(!$needsInherited || $inherited, 'A derived table with requested aliases requires MySQL 8.0 or later.');
        Check::input(!$inherited || $needsInherited, 'Retaining inherited derived aliases requires a legacy SELECT with requested aliases.');
        if ($inherited) {
            $this->check($child);
        }
    }

    /**
     * Tells whether this SELECT needs its inherited names to retain alias diagnostics.
     */
    public function needsInherited(Select|LocalProjection|WithProjection $child): bool
    {
        return $child instanceof Select
            && ($child->release === GrammarRelease::MySql5651 || $child->release === GrammarRelease::MySql5744)
            && $this->requiresRequested($child);
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
