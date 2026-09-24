<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Routine\Program;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Statement\StatementBinder;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Routine\Body\AssignmentStatement;
use SqlSemantics\Model\Definition\Routine\Body\Declaration\LocalAssignment;
use SqlSemantics\Model\Definition\Routine\Body\Declaration\TriggerRowAssignment;
use SqlSemantics\Model\Scalar\Reference\LocalVariableReference;
use SqlSemantics\Model\Scalar\Reference\TriggerColumn;
use SqlSemantics\Model\Scalar\Reference\UnresolvedColumnReference;
use SqlSemantics\Model\Statement\Configuration\SetStatement;
use SqlSemantics\Model\Trigger\Timing;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Binds SET items that assign local variables or NEW trigger columns; the other items keep their ordinary setting forms.
 * @visibility SqlSemantics
 */
final class Assignments
{
    /**
     * Returns null when no item of the SET assigns a program target.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function bind(Node $statement, ProgramFrame $frame): ?AssignmentStatement
    {
        $items = Tree::outer($statement, ['option_value_no_option_type', 'option_value_following_option_type']);
        $targets = array_map(static fn (Node $item): LocalVariableReference|TriggerColumn|UnresolvedColumnReference|null => $item->name === 'option_value_no_option_type' ? self::target($item, $frame) : null, $items);
        if (array_filter($targets, static fn ($target): bool => $target !== null) === []) {
            return null;
        }
        $settings = [];
        if (in_array(null, $targets, true)) {
            $bound = (new StatementBinder($frame->context->tables))->node($statement, $statement, $frame->context);
            $settings = $bound instanceof SetStatement ? $bound->settings : [];
            if (count($settings) !== count($items)) {
                throw new UnclassifiedSql('A stored program SET requires one setting per item.');
            }
        }
        $assignments = [];
        foreach ($items as $index => $item) {
            $target = $targets[$index];
            if ($target === null) {
                $assignments[] = $settings[$index];
                continue;
            }
            $value = Tree::outer($item, ['set_expr_or_default'])[0] ?? $item;
            $expression = Tree::child($value, ['expr']) ?? throw new InvalidSql(InputViolation::ProgramObject, $value);
            $assignments[] = $target instanceof LocalVariableReference ? new LocalAssignment($target, $frame->expression($expression)) : new TriggerRowAssignment($target, $frame->expression($expression));
        }
        return new AssignmentStatement($assignments);
    }

    /**
     * Resolves a local variable or a NEW column of a BEFORE trigger; OLD and a NEW row the event or timing cannot change are diagnosed.
     * @throws InvalidSql
     */
    public static function target(Node $item, ProgramFrame $frame): LocalVariableReference|TriggerColumn|UnresolvedColumnReference|null
    {
        $lvalue = Tree::child($item, ['lvalue_variable', 'internal_variable_name']);
        if ($lvalue === null || Tree::child($item, ['opt_set_var_ident_type']) !== null) {
            return null;
        }
        $parts = $frame->context->tables->identifiers->parts($lvalue);
        $names = $frame->names();
        if (count($parts) === 1 && $names->variable($parts[0]) !== null) {
            return Cursors::variable($lvalue, $frame);
        }
        if (count($parts) !== 2 || !$names->trigger || !in_array(strtolower($parts[0]), ['new', 'old'], true)) {
            return null;
        }
        if (strtolower($parts[0]) === 'old' || $frame->timing === Timing::After) {
            throw new InvalidSql(InputViolation::ProgramObject, $lvalue);
        }
        $column = $names->resolve($frame->scope(), $parts, $lvalue);
        return $column instanceof TriggerColumn || $column instanceof UnresolvedColumnReference ? $column : throw new InvalidSql(InputViolation::ProgramObject, $lvalue);
    }
}
