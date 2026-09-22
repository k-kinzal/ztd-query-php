<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query;

use SqlSemantics\Model\BoundQuery;
use SqlSemantics\Model\ColumnBinding;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Scalar\Reference\ColumnReference;
use SqlSemantics\Model\Scalar\Reference\Wildcard;
use SqlSemantics\Model\Scalar\Value\SetColumn;
use SqlSemantics\Model\Scalar\Value\ValuesColumn;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Builds query results exclusively from their typed row, table, or set operands.
 * @visibility SqlSemantics
 */
final class DerivedResults
{
    /**
     * @param non-empty-list<list<Expression>> $rows Validated equal-width rows
     * @return list<OutputColumn>
     */
    public static function rows(Origin $origin, array $rows): array
    {
        $outputs = [];
        foreach ($rows[0] as $ordinal => $first) {
            $alternatives = array_map(static fn (array $row): Expression => $row[$ordinal], $rows);
            $value = count($rows) === 1 ? $first : new ValuesColumn(AlternativeFacts::of($origin->dialect, $alternatives), $origin->source, $alternatives);
            $outputs[] = new OutputColumn($ordinal, 'column' . ($ordinal + 1), $value);
        }
        return $outputs;
    }

    /**
     * @return list<OutputColumn>
     */
    public static function table(Origin $origin, TableReference|\SqlSemantics\Model\Relation\CteReference $table): array
    {
        if (!$table->declaration->resolved) {
            $facts = new ExpressionFacts(TypeDescriptor::builtin($origin->dialect, 'unknown'), Nullability::Unknown, []);
            return [new OutputColumn(0, null, new Wildcard($facts, $origin->source, [$table->declaration->name]))];
        }
        $outputs = [];
        foreach ($table->declaration->columns as $ordinal => $column) {
            $binding = new ColumnBinding($table->id, $table->declaration, $column);
            $value = new ColumnReference(new ExpressionFacts($column->type, $column->nullability, []), $origin->source, $binding, isset($table->resultExpressions()[$ordinal]) ? [$table->resultExpressions()[$ordinal]] : [], [$table->declaration->name, $column->name]);
            $outputs[] = new OutputColumn($ordinal, $column->name, $value);
        }
        return $outputs;
    }

    /**
     * @return list<OutputColumn>
     */
    public static function compound(Origin $origin, BoundQuery $left, BoundQuery $right, SetOperator $operator): array
    {
        $outputs = [];
        foreach ($left->resultColumns() as $ordinal => $output) {
            $alternatives = [AlternativeFacts::setInput($output->expression)];
            if (isset($right->resultColumns()[$ordinal])) {
                $alternatives[] = AlternativeFacts::setInput($right->resultColumns()[$ordinal]->expression);
            }
            $value = new SetColumn(AlternativeFacts::of($origin->dialect, $alternatives), $origin->source, $operator, $alternatives);
            $outputs[] = new OutputColumn($ordinal, $output->name, $value);
        }
        return $outputs;
    }

}
