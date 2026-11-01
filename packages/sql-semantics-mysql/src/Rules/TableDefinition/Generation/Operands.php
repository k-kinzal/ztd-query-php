<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\TableDefinition\Generation;

use ReflectionClass;
use SqlSemantics\Platform\MySql\Statement\Call\ClockCall;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Call\KeywordCall;
use SqlSemantics\Platform\MySql\Statement\Expression\Access\InsertedColumn;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\Generation\OperandKind;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\Generation\RefusedOperand;
use SqlSemantics\Platform\MySql\Statement\Variable\SystemVariable;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Query;

/**
 * Checks the operands of a generation expression or an expression default before ordinary scalar preparation.
 *
 * Full-text search has a forbidden generation role before its search operand.
 * Other child expressions complete before their enclosing function or query. A
 * forbidden call inside a subquery therefore precedes that subquery's role
 * refusal. This visits semantic operands only, independently of catalog state.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Operands
{
    /**
     * Finds the first intrinsic refusal of a column expression in completion order.
     *
     * @param \SqlSemantics\Platform\MySql\Statement\Table\Problem\Generation\ExpressionRole $role The column expression the operands belong to
     */
    public function problem(Node $expression, Name $column, ?\SqlSemantics\Contract\GrammarRelease $release = null, \SqlSemantics\Platform\MySql\Statement\Table\Problem\Generation\ExpressionRole $role = \SqlSemantics\Platform\MySql\Statement\Table\Problem\Generation\ExpressionRole::Generated): ?RefusedOperand
    {
        if ($expression instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Access\FullTextSearch || $expression instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Access\DeferredFullTextSearch) {
            return new RefusedOperand($column, OperandKind::FunctionCall, 'match', role: $role);
        }
        foreach ($this->children($expression) as $child) {
            if (($problem = $this->problem($child, $column, $release, $role)) !== null) {
                return $problem;
            }
        }
        return $this->role($expression, $column, $release, $role);
    }

    /**
     * Finds the first refused operand of the expression defaults among column attributes.
     *
     * @param list<\SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnAttribute> $attributes The attributes in written order
     */
    public function defaults(array $attributes, Name $column, ?\SqlSemantics\Contract\GrammarRelease $release = null): ?RefusedOperand
    {
        foreach ($attributes as $attribute) {
            if ($attribute instanceof \SqlSemantics\Platform\MySql\Statement\Table\Column\DefaultExpression && ($problem = $this->problem($attribute->expression, $column, $release, \SqlSemantics\Platform\MySql\Statement\Table\Problem\Generation\ExpressionRole::Default)) !== null) {
                return $problem;
            }
        }
        return null;
    }

    /**
     * Reads semantic constructor operands in their declared order. PHP property
     * order puts explicit list properties before promoted scalar properties.
     *
     * @return list<Node>
     */
    public function children(Node $expression): array
    {
        $children = [];
        $values = get_object_vars($expression);
        foreach ((new ReflectionClass($expression))->getConstructor()?->getParameters() ?? [] as $parameter) {
            $value = $values[$parameter->getName()] ?? null;
            foreach (is_array($value) ? $value : [$value] as $child) {
                if ($child instanceof Node) {
                    $children[] = $child;
                }
            }
        }
        return $children;
    }

    /**
     * Identifies the enclosing operand's refusal after its children complete.
     *
     * @param \SqlSemantics\Platform\MySql\Statement\Table\Problem\Generation\ExpressionRole $role The column expression the operand belongs to
     */
    public function role(Node $expression, Name $column, ?\SqlSemantics\Contract\GrammarRelease $release = null, \SqlSemantics\Platform\MySql\Statement\Table\Problem\Generation\ExpressionRole $role = \SqlSemantics\Platform\MySql\Statement\Table\Problem\Generation\ExpressionRole::Generated): ?RefusedOperand
    {
        if ($expression instanceof \SqlSemantics\Platform\MySql\Statement\Expression\RowOperand) {
            return new RefusedOperand($column, OperandKind::Row, role: $role);
        }
        if ($expression instanceof \SqlSemantics\Platform\MySql\Statement\Literal\Parameter) {
            return new RefusedOperand($column, OperandKind::Parameter, role: $role);
        }
        if ($expression instanceof FunctionCall && !$this->native($expression, $release)) {
            $name = ($expression->schema === null ? '' : '`' . str_replace('`', '``', $expression->schema->value) . '`.') . '`' . str_replace('`', '``', $expression->name->value) . '`';
            return new RefusedOperand($column, OperandKind::StoredFunction, $name, role: $role);
        }
        if ($expression instanceof Query) {
            return new RefusedOperand($column, OperandKind::Subquery, role: $role);
        }
        if ($expression instanceof UserVariable || $expression instanceof SystemVariable) {
            return new RefusedOperand($column, OperandKind::Variable, role: $role);
        }
        $name = match (true) {
            $expression instanceof FunctionCall => $expression->schema === null ? strtolower($expression->name->value) : null,
            $expression instanceof KeywordCall => strtolower($expression->function->value),
            $expression instanceof ClockCall => strtolower($expression->clock->value),
            $expression instanceof InsertedColumn => 'values',
            default => null,
        };
        if ($name === 'name_const') {
            return new RefusedOperand($column, OperandKind::Subquery, role: $role);
        }
        $function = $name === null ? null : ((new Functions())->names($role)[$name] ?? null);
        return $function === null ? null : new RefusedOperand($column, OperandKind::FunctionCall, $function, role: $role);
    }
    /**
     * Keeps generic stored calls distinct from calls resolved by a native catalog.
     * An unprofiled constructor retains names native in at least one supported release.
     */
    public function native(FunctionCall $call, ?\SqlSemantics\Contract\GrammarRelease $release): bool
    {
        foreach ($release === null ? \SqlSemantics\Contract\GrammarRelease::cases() : [$release] as $candidate) {
            if ($candidate->database() === 'mysql' && $call->native($candidate)) {
                return true;
            }
        }
        return false;
    }
}
