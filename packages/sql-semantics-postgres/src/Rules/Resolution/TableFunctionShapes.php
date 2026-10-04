<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Resolution;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Invocation\JsonChecks;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonValueExpression;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonBehaviorClause;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonFunctionKind;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\QueryMisuse;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\QueryMisuseRule;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\Json\JsonExistsColumn;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\Json\JsonNestedColumns;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\Json\JsonOrdinalityColumn;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\Json\JsonTable;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\Json\JsonTableColumn;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\Json\JsonValueColumn;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\Xml\XmlColumnOptionKind;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\Xml\XmlTable;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\Xml\XmlTableColumn;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Shape\RowShape;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

/**
 * Derives the columns of XMLTABLE and JSON_TABLE used as FROM items.
 *
 * Rule: PG-XMLTABLE-001 / PG-JSON-TABLE-001. Every expression is derived
 * where the FROM items before the table are visible. A FOR ORDINALITY column
 * is an integer that is never NULL; every other column has its declared type
 * and can be NULL, except an XMLTABLE column declared NOT NULL. The nested
 * columns of JSON_TABLE follow in written order. XMLTABLE reports a second
 * FOR ORDINALITY column, repeated PATH, DEFAULT or NULL options, and an
 * option word other than `path` and `default`; JSON_TABLE reports a row path
 * that is not a string constant and the behaviors a column does not accept.
 * The column aliases rename the columns (PG-COLUMN-ALIAS-001).
 * Source: https://www.postgresql.org/docs/17/functions-xml.html#FUNCTIONS-XML-PROCESSING-XMLTABLE,
 * https://www.postgresql.org/docs/17/functions-json.html#FUNCTIONS-SQLJSON-TABLE. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class TableFunctionShapes
{
    /**
     * Derives XMLTABLE and answers its columns.
     */
    public function xml(XmlTable $table, Derivation $derivation, Environment $environment): RelationFact
    {
        foreach ($table->namespaces as $namespace) {
            $derivation->scalar($namespace->uri, $environment);
        }
        $derivation->scalar($table->row, $environment);
        $table->passing->deriveClause($derivation, $environment);
        $slots = [];
        $ordinality = 0;
        foreach ($table->columns as $column) {
            if ($column->type === null) {
                $ordinality++;
                $slots[] = new OutputSlot($column->name, new Known(Builtin::Int4), Nullability::NotNull);
                continue;
            }
            $column->type->deriveClause($derivation, $environment);
            $slots[] = new OutputSlot($column->name, $column->type->typeFact($derivation->context), $this->options($column, $derivation, $environment) ? Nullability::NotNull : Nullability::Nullable);
        }
        if ($ordinality > 1) {
            $derivation->report(new QueryMisuse(QueryMisuseRule::RepeatedOrdinality));
        }

        return new RelationFact((new ColumnAliases())->apply(new RowShape($slots), $table->alias, $table->aliases, $derivation));
    }

    /**
     * Derives the options of an XMLTABLE column, reports the misused ones and tells whether NOT NULL is written.
     */
    public function options(XmlTableColumn $column, Derivation $derivation, Environment $environment): bool
    {
        $counts = ['default' => 0, 'path' => 0, 'null' => 0];
        $notNull = false;
        foreach ($column->options as $option) {
            if ($option->value !== null) {
                $derivation->scalar($option->value, $environment);
            }
            $word = $option->word === null ? null : $option->word->value;
            $key = match ($option->kind) {
                XmlColumnOptionKind::Default => 'default',
                XmlColumnOptionKind::Path => 'path',
                XmlColumnOptionKind::NotNull, XmlColumnOptionKind::Null => 'null',
                XmlColumnOptionKind::Named => $word === 'default' || $word === 'path' ? $word : null,
            };
            $notNull = $notNull || $option->kind === XmlColumnOptionKind::NotNull;
            if ($key === null) {
                $derivation->report(new QueryMisuse(QueryMisuseRule::UnrecognizedColumnOption, $option->word));
                continue;
            }
            $counts[$key]++;
        }
        foreach ([[$counts['null'], new QueryMisuse(QueryMisuseRule::RedundantNullability, $column->name)], [$counts['default'], new QueryMisuse(QueryMisuseRule::RepeatedDefault)], [$counts['path'], new QueryMisuse(QueryMisuseRule::RepeatedPath)]] as [$count, $problem]) {
            if ($count > 1) {
                $derivation->report($problem);
            }
        }

        return $notNull;
    }

    /**
     * Derives JSON_TABLE and answers its columns.
     */
    public function json(JsonTable $table, Derivation $derivation, Environment $environment): RelationFact
    {
        if ($table->context instanceof JsonValueExpression) {
            $table->context->deriveValue($derivation, $environment, true);
        } else {
            $table->context->deriveClause($derivation, $environment);
        }
        $derivation->scalar($table->path, $environment);
        if (!$table->path instanceof Constant || !$table->path->value instanceof StringConstant) {
            $derivation->report(new QueryMisuse(QueryMisuseRule::PathNotConstant));
        }
        foreach ($table->passing as $argument) {
            $argument->deriveClause($derivation, $environment);
        }
        $this->clause($table->onError, $derivation, $environment);

        return new RelationFact((new ColumnAliases())->apply(new RowShape($this->columns($table->columns, $derivation, $environment)), $table->alias, $table->aliases, $derivation));
    }

    /**
     * Derives JSON_TABLE column definitions, the nested ones included, and answers their slots in order.
     *
     * @param list<JsonTableColumn> $columns
     * @return list<OutputSlot>
     */
    public function columns(array $columns, Derivation $derivation, Environment $environment): array
    {
        $slots = [];
        $pending = array_reverse($columns);
        while ($pending !== []) {
            $column = array_pop($pending);
            if ($column instanceof JsonNestedColumns) {
                array_push($pending, ...array_reverse($column->columns));
            } elseif ($column instanceof JsonOrdinalityColumn) {
                $slots[] = new OutputSlot($column->name, new Known(Builtin::Int4), Nullability::NotNull);
            } elseif ($column instanceof JsonValueColumn || $column instanceof JsonExistsColumn) {
                $slots[] = $this->typed($column, $derivation, $environment);
            }
        }

        return $slots;
    }

    /**
     * Derives a value or EXISTS column and answers its slot.
     */
    public function typed(JsonValueColumn|JsonExistsColumn $column, Derivation $derivation, Environment $environment): OutputSlot
    {
        $column->type->deriveClause($derivation, $environment);
        if ($column instanceof JsonExistsColumn) {
            $this->clause($column->onError, $derivation, $environment);
            $kind = JsonFunctionKind::Exists;
            $behavior = $column->onError;
        } else {
            foreach ([$column->format, $column->wrapper, $column->quotes, $column->behavior] as $clause) {
                $this->clause($clause, $derivation, $environment);
            }
            $kind = $column->format !== null || $column->wrapper !== null || $column->quotes !== null ? JsonFunctionKind::Query : JsonFunctionKind::Value;
            $behavior = $column->behavior;
        }
        if ($behavior instanceof JsonBehaviorClause) {
            (new JsonChecks())->behaviors($derivation, $kind, $behavior, $column->name);
        }

        return new OutputSlot($column->name, $column->type->typeFact($derivation->context), Nullability::Nullable);
    }

    /**
     * Derives an optional clause.
     */
    public function clause(?Node $clause, Derivation $derivation, Environment $environment): void
    {
        if ($clause instanceof Clause) {
            $clause->deriveClause($derivation, $environment);
        }
    }
}
