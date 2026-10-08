<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Compile;

use MySqlMemory\Error\Family\AdministrationError;
use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Leaf\Assignment;
use MySqlMemory\Evaluation\Leaf\ColumnRead;
use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Evaluation\Leaf\Outer;
use MySqlMemory\Evaluation\Leaf\ProgramRead;
use MySqlMemory\Evaluation\Leaf\SystemVariableRead;
use MySqlMemory\Evaluation\Leaf\UserVariableRead;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Plan\Window\Windowing;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Variable\Scope as VariableScope;
use SqlSemantics\Platform\MySql\Statement\Expression\Access\InsertedColumn;
use SqlSemantics\Platform\MySql\Statement\Literal\Parameter;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\OutputOrdinal;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Platform\MySql\Statement\Variable\SystemVariable;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableAssignment;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableScope as Written;
use SqlSemantics\Statement\Reference\Column\AliasTarget;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Shape\Field as ShapeField;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Type\Nullability;

/**
 * Compiles names: columns, select items named by alias or position, parameters and variables.
 *
 * A column is read at the position its relation occurrence has in the row of the block that
 * holds it; the facts name the occurrence and the column. A name that denotes a variable of the
 * running stored program reads the variable. A column of a stored table is found by
 * its declaration, so invisible columns are found too.
 *
 * @visibility MySqlMemory\Evaluation
 */
final class Names
{
    /**
     * @param Compiler $compiler The compiler of the statement
     */
    public function __construct(public readonly Compiler $compiler)
    {
    }

    /**
     * Compiles a column name.
     *
     * @throws \MySqlMemory\Error\SqlError When the name does not resolve to one column
     */
    public function column(ColumnUse $use, Scope $scope): Evaluable
    {
        $fact = $this->compiler->facts->scalar($use);
        $resolution = $fact->resolution;
        if ($resolution instanceof AliasTarget) {
            return $this->field($resolution->field, $scope);
        }
        if (!$resolution instanceof ResolvedColumn) {
            throw QueryError::BadField->error($use->name->value, 'field list');
        }
        $program = $this->compiler->connection->program;
        if ($program !== null && $scope->locate($resolution->relation) === null) {
            $variable = $use->qualifier === null ? $program->find($resolution->relation, $use->name->value) : $program->row($use->qualifier->name->value)?->variable($use->name->value);
            if ($variable !== null) {
                return new ProgramRead($variable);
            }
        }

        return $this->resolved($resolution, $scope, $fact->nullability !== Nullability::NotNull);
    }

    /**
     * Compiles a column a resolution names; a JSON column is the source its values are named by in warnings.
     */
    public function resolved(ResolvedColumn $resolution, Scope $scope, bool $nullable): Evaluable
    {
        $located = $scope->locate($resolution->relation);
        if ($located === null) {
            throw StatementError::NotSupportedYet->error('a column of a relation outside the statement');
        }
        [$depth, $holder] = $located;
        $id = spl_object_id($resolution->relation);
        $ordinal = $this->position($holder, $resolution);
        $domain = $holder->columns[$id][$ordinal]->withNullable($nullable);
        if ($domain->kind === Kind::Json) {
            $domain = $domain->withSource($holder->names[$id][$ordinal] ?? '');
        }

        return new ColumnRead($domain, $holder->offsets[$id] + $ordinal, $depth);
    }

    /**
     * Answers the position within its relation occurrence of the column a resolution names.
     */
    public function position(Scope $holder, ResolvedColumn $resolution): int
    {
        $id = spl_object_id($resolution->relation);
        if (isset($holder->tables[$id])) {
            $declaration = $resolution->slot->declaration();
            foreach ($holder->tables[$id]->columns as $position => $column) {
                if ($column->declaration === $declaration) {
                    return $position;
                }
            }
        }
        $slots = $this->compiler->facts->relation($resolution->relation)->shape->slots;
        for ($slot = $resolution->slot; $slot instanceof OutputSlot; $slot = $slot->origin) {
            $position = array_search($slot, $slots, true);
            if (is_int($position)) {
                return $position;
            }
        }

        throw StatementError::NotSupportedYet->error('a column outside the shape of its relation');
    }

    /**
     * Compiles a select item an alias or a position names.
     *
     * An item holding a window function is read by its alias only where the window functions of its
     * block are computed, in the ORDER BY of the block; elsewhere, in HAVING or a subquery, it is
     * refused with ER_WINDOW_INVALID_WINDOW_FUNC_ALIAS_USE (verified on a live 8.4 server).
     *
     * @throws \MySqlMemory\Error\SqlError When the item holds a window function not computed yet
     */
    public function field(ShapeField $field, Scope $scope): Evaluable
    {
        if ($scope->output) {
            return new ColumnRead($scope->columns[(int) array_key_first($scope->columns)][$field->position], $field->position);
        }
        if ($field->expression !== null) {
            foreach ((new Walker())->find($field->expression, Scalar::class, false) as $node) {
                if (Windowing::windowed($node) && ($scope->bound($node)[0] ?? null) !== 0) {
                    throw QueryError::WindowAliasMisplaced->error($field->name->value ?? '');
                }
            }

            return $this->compiler->compile($field->expression, $scope);
        }
        if ($field->resolution instanceof ResolvedColumn) {
            return $this->resolved($field->resolution, $scope, $field->nullability !== Nullability::NotNull);
        }

        throw StatementError::NotSupportedYet->error('a select item without an expression');
    }

    /**
     * Compiles VALUES(column): the value the row an INSERT was to write has for a column.
     *
     * @throws \MySqlMemory\Error\SqlError Outside ON DUPLICATE KEY UPDATE, where it is NULL
     */
    public function inserted(InsertedColumn $node, Scope $scope): Evaluable
    {
        $definition = $scope->inserted;
        if ($definition === null) {
            return new Constant(Domain::null(), null);
        }
        foreach ($definition->columns as $position => $column) {
            if (strcasecmp($column->name, $node->column->name->value) === 0) {
                return new ColumnRead($column->domain, count($definition->columns) + $position);
            }
        }

        throw QueryError::BadField->error($node->column->name->value, 'field list');
    }

    /**
     * Compiles a position in ORDER BY or GROUP BY.
     */
    public function ordinal(OutputOrdinal $ordinal, Scope $scope): Evaluable
    {
        $resolution = $this->compiler->facts->scalar($ordinal)->resolution;
        if (!$resolution instanceof AliasTarget) {
            throw QueryError::BadField->error($ordinal->literal->text, 'order clause');
        }

        return $this->field($resolution->field, $scope);
    }

    /**
     * Compiles an expression of an enclosing block bound for a node.
     */
    public function outer(Evaluable $inner, int $depth): Evaluable
    {
        return new Outer($inner, $depth);
    }

    /**
     * Compiles a parameter marker into the value bound to it.
     */
    public function parameter(Parameter $parameter): Evaluable
    {
        $bound = $this->compiler->connection->parameters;
        $index = $parameter->position ?? $this->compiler->parameterIndex($parameter);
        if (!isset($bound[$index])) {
            return new Constant(Domain::null(), null);
        }

        return new Constant($this->compiler->resolved($parameter) ?? $bound[$index][1], $bound[$index][0]);
    }

    /**
     * Compiles a user variable read.
     */
    public function userVariable(UserVariable $variable): Evaluable
    {
        [$value, $domain] = $this->compiler->connection->variables->user($variable->name->value);
        if ($value === null) {
            $domain = Domain::string(0, Collation::binary(), Field::MediumBlob)->withCollation($this->compiler->settings->connectionCollation, Coercibility::Implicit);
        }

        return new UserVariableRead($variable->name->value, $domain->withNullable(true));
    }

    /**
     * Compiles an assignment to a user variable.
     */
    public function assignment(VariableAssignment $assignment, Scope $scope): Evaluable
    {
        $value = $this->compiler->compile($assignment->value, $scope);

        return new Assignment($assignment->target->name->value, $value, $this->stored($value->domain()));
    }

    /**
     * Answers the domain a user variable holds an assigned value in.
     */
    public function stored(Domain $domain): Domain
    {
        return match ($domain->kind) {
            Kind::Integer, Kind::Year, Kind::Bit => Domain::integer(Field::LongLong, 21, $domain->unsigned),
            Kind::Decimal => Domain::decimal(65, $domain->decimals),
            Kind::Double => Domain::double(),
            Kind::Null => Domain::string(0, Collation::binary(), Field::MediumBlob),
            Kind::String, Kind::Date, Kind::Time, Kind::DateTime, Kind::Json => Domain::string(16777216, $domain->collation, Field::MediumBlob)->withCollation($domain->collation, Coercibility::Implicit),
        };
    }

    /**
     * Compiles a system variable read; in MySQL 5.6 and 5.7 a string is as long as the value it holds (verified on live 5.6.51 and 5.7.44 servers).
     *
     * @throws \MySqlMemory\Error\SqlError When the server has no such variable, or not in the scope read
     */
    public function systemVariable(SystemVariable $variable): Evaluable
    {
        $name = strtolower($variable->name->value);
        $definition = $this->compiler->connection->variables->catalog->find($name);
        if ($definition === null) {
            throw AdministrationError::UnknownSystemVariable->error(($variable->instance === null ? '' : $variable->instance->value . '.') . $variable->name->value);
        }
        $scope = match ($variable->scope) {
            Written::Global => VariableScope::Global,
            Written::Session => VariableScope::Session,
            null => VariableScope::Both,
            Written::Persist, Written::PersistOnly => VariableScope::Global,
        };
        if ($scope === VariableScope::Global && !$definition->reach->global()) {
            throw AdministrationError::IncorrectGlobalLocalVariable->error($variable->name->value, 'SESSION');
        }
        if ($scope === VariableScope::Session && !$definition->reach->session()) {
            throw AdministrationError::IncorrectGlobalLocalVariable->error($variable->name->value, 'GLOBAL');
        }
        $domain = Domain::of($definition->domain, true);
        if ($domain->kind === Kind::String && $this->compiler->settings->legacy()) {
            $value = $this->compiler->connection->variables->system($definition, $scope === VariableScope::Global ? VariableScope::Global : VariableScope::Session);
            $domain = new Domain(Kind::String, $domain->field, mb_strlen((string) $value, 'UTF-8'), $domain->decimals, false, $domain->collation, true, [], $domain->coercibility);
        }

        return new SystemVariableRead($definition, $scope === VariableScope::Global ? VariableScope::Global : VariableScope::Session, $domain->withNullable(true));
    }

    /**
     * Compiles DEFAULT(column): the default of the column.
     *
     * The default CURRENT_TIMESTAMP reads as NULL, or as the zero value when the column is NOT NULL;
     * an AUTO_INCREMENT column without a default reads as 0, and a default written as an expression
     * is ER_DEFAULT_AS_VAL_GENERATED (verified on a live 8.4 server). In the rows of an INSERT, the
     * column is one of the table written, which the scope holds without reading its rows.
     *
     * @throws \MySqlMemory\Error\SqlError When the column has no default, or a default written as an expression
     */
    public function default(\SqlSemantics\Platform\MySql\Statement\Expression\Access\DefaultOfColumn $node, Scope $scope): Evaluable
    {
        $resolution = $this->compiler->facts->scalar($node->column)->resolution;
        if (!$resolution instanceof ResolvedColumn) {
            throw QueryError::BadField->error($node->column->name->value, 'field list');
        }
        $located = $scope->locate($resolution->relation);
        $holder = $located[1] ?? (isset($scope->tables[spl_object_id($resolution->relation)]) ? $scope : null);
        $definition = $holder === null ? null : ($holder->tables[spl_object_id($resolution->relation)] ?? null);
        if ($holder === null || $definition === null) {
            throw StatementError::NotSupportedYet->error('DEFAULT of a column of a derived table');
        }
        $column = $definition->columns[$this->position($holder, $resolution)];
        if (!$column->default->declared && $column->autoIncrement) {
            return new Constant($column->domain->withNullable(false), 0);
        }
        if (!$column->default->declared) {
            throw DataError::NoDefaultForField->error($column->name);
        }
        if ($column->default->now) {
            return new Constant($column->domain, $column->nullable() ? null : '0000-00-00 00:00:00' . ($column->domain->decimals > 0 ? '.' . str_repeat('0', $column->domain->decimals) : ''));
        }
        if ($column->default->expression !== null) {
            throw QueryError::DefaultOfExpression->error();
        }

        return new Constant($column->domain->withNullable($column->default->value === null), $column->default->value);
    }
}
