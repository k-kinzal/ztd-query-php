<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Resolution;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\OutputNaming;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\QueryMisuse;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\QueryMisuseRule;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\FunctionTable;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\TableFunction;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypedColumn;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Missing\MissingInput;
use SqlSemantics\Statement\Reference\Missing\UndeclaredRoutine;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Shape\RowShape;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

/**
 * Derives the columns of a function call used as a FROM item.
 *
 * Rule: PG-FUNCTION-TABLE-001. Each call is derived where the FROM items
 * before it are visible. A call with column definitions has those columns,
 * of the defined types; definitions for a call of a known type other than
 * `record` are reported. A call of a base type has one column, named by the
 * correlation name when it is the only call and has one, else by the
 * function name. A call of type `record` without definitions has the columns
 * of the routine's declaration, which the context cannot hold: the shape is
 * open on the routine. A call whose type depends on missing declarations
 * leaves the shape open on them. Under ROWS FROM the columns of the calls
 * follow each other and can all be NULL, because shorter results are padded
 * with NULLs. WITH ORDINALITY adds the column `ordinality` of type bigint.
 * The column aliases then rename the columns (PG-COLUMN-ALIAS-001).
 * Source: https://www.postgresql.org/docs/17/queries-table-expressions.html#QUERIES-TABLEFUNCTIONS,
 * https://www.postgresql.org/docs/17/sql-select.html#SQL-FROM. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class FunctionShapes
{
    /**
     * Derives every call and answers the columns of the occurrence.
     */
    public function derive(FunctionTable $table, Derivation $derivation, Environment $environment): RelationFact
    {
        $slots = [];
        $missing = [];
        $single = count($table->functions) === 1;
        foreach ($table->functions as $function) {
            $fact = $derivation->scalar($function->call, $environment);
            $definitions = $function->definitions !== [] ? $function->definitions : ($single ? $table->definitions : []);
            if ($definitions !== []) {
                array_push($slots, ...$this->defined($definitions, $fact, $derivation, $environment));
                continue;
            }
            $name = ($single ? $table->alias : null) ?? $this->functionName($function) ?? new Name('?column?');
            if ($fact->type instanceof Known && $fact->type->descriptor === Builtin::Record) {
                $missing[] = new UndeclaredRoutine(new QualifiedName($name));
            } elseif ($fact->type instanceof Dependent) {
                array_push($missing, ...$fact->type->missing);
            } elseif (!$fact->type instanceof Invalid) {
                $slots[] = new OutputSlot($name, $fact->type, $single ? $fact->nullability : Nullability::Nullable);
            }
        }
        if (!$single) {
            $slots = $this->padded($slots);
        }
        if ($table->ordinality) {
            $slots[] = new OutputSlot(new Name('ordinality'), new Known(Builtin::Int8), Nullability::NotNull);
        }

        return new RelationFact((new ColumnAliases())->apply(new RowShape($slots, $this->distinct($missing)), $table->alias, $table->columns, $derivation));
    }

    /**
     * Answers the name a call gives its FROM item and its column: the function name.
     */
    public function functionName(TableFunction $function): ?Name
    {
        return $function->call instanceof OutputNaming ? $function->call->outputName() : null;
    }

    /**
     * Derives column definitions and answers their slots.
     *
     * @param list<TypedColumn> $definitions
     * @return list<OutputSlot>
     */
    public function defined(array $definitions, ScalarFact $call, Derivation $derivation, Environment $environment): array
    {
        if ($call->type instanceof Known && $call->type->descriptor !== Builtin::Record) {
            $derivation->report(new QueryMisuse(QueryMisuseRule::DefinitionsForBaseType));
        }
        $slots = [];
        foreach ($definitions as $definition) {
            $definition->deriveClause($derivation, $environment);
            $slots[] = new OutputSlot($definition->name, $definition->type->typeFact($derivation->context), Nullability::Nullable);
        }

        return $slots;
    }

    /**
     * Answers the slots made able to be NULL.
     *
     * @param list<OutputSlot> $slots
     * @return list<OutputSlot>
     */
    public function padded(array $slots): array
    {
        $padded = [];
        foreach ($slots as $slot) {
            $padded[] = $slot->nullability === Nullability::NotNull ? new OutputSlot($slot->name, $slot->type, Nullability::Nullable, $slot->column, $slot->origin) : $slot;
        }

        return $padded;
    }

    /**
     * Removes repeated missing inputs.
     *
     * @param list<MissingInput> $missing
     * @return list<MissingInput>
     */
    public function distinct(array $missing): array
    {
        $result = [];
        foreach ($missing as $input) {
            if (!in_array($input, $result, true)) {
                $result[] = $input;
            }
        }

        return $result;
    }
}
