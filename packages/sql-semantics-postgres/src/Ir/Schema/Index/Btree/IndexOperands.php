<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree;

use SqlSemantics\Platform\PostgreSql\Ir\Expression\TextCollation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\Lookup\StockLookup;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\IndexHeading;
use SqlSemantics\Platform\PostgreSql\Statement\Query\NullsOrder;
use SqlSemantics\Platform\PostgreSql\Statement\Query\SortDirection;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Index\ColumnKey;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Index\IndexElement;

/**
 * Binds positional operands and makes default B-tree ordering explicit.
 *
 * @visibility SqlSemantics
 */
final class IndexOperands
{
    /**
     * Binds and orders one written key.
     */
    public function key(IndexHeading $table, IndexElement $element, bool $stockFirst): IndexKey
    {
        $descending = $element->direction === SortDirection::Descending;
        $nullsFirst = $element->nulls === NullsOrder::First || ($element->nulls === null && $descending);
        $order = $descending
            ? ($nullsFirst ? IndexOrder::DescendingNullsFirst : IndexOrder::DescendingNullsLast)
            : ($nullsFirst ? IndexOrder::AscendingNullsFirst : IndexOrder::AscendingNullsLast);

        $slot = $this->slot($table, $element);
        $collation = $table->columnCollations[$slot] ?? null;
        $unsupported = 'An explicitly collated PostgreSQL B-tree key requires stock pg_catalog.C and its default text operator class.';
        if ($element->collation !== null) {
            Check::input(StockLookup::matches($element->collation->qualified(), 'C', $stockFirst), $unsupported);
            $collation = TextCollation::C;
        }
        Check::input($element->operatorClass === null
            || ($collation !== null && StockLookup::matches($element->operatorClass->qualified(), 'text_ops', $stockFirst)), $unsupported);

        return new IndexKey($slot, $order, $collation);
    }

    /**
     * Binds one INCLUDE occurrence.
     */
    public function covering(IndexHeading $table, IndexElement $element): IncludedColumn
    {
        Check::input($element->direction === null && $element->nulls === null, 'An included column cannot specify key order.');
        Check::input($element->collation === null && $element->operatorClass === null, 'An included column cannot specify key comparison options.');

        return new IncludedColumn($this->slot($table, $element));
    }

    /**
     * Finds the exact physical position and checks that native default semantics apply.
     *
     * @throws InvalidConstruction When the column is unavailable
     */
    public function slot(IndexHeading $table, IndexElement $element): int
    {
        Check::input(
            $element->key instanceof ColumnKey && $element->classOptions === [],
            'A B-tree operand needs a direct column and its default operator class.'
        );
        foreach ($table->columns as $position => $column) {
            if ($column->value === $element->key->column->value) {
                return $position;
            }
        }
        throw new InvalidConstruction('The indexed column does not belong to the resolved table.');
    }
}
