<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Validation;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ResultStatement;
use SqlSemantics\Model\Write\Insertion;

/**
 * Checks the width and dialect invariants of row-producing operations.
 * @visibility SqlSemantics
 */
final class RowShape
{
    /**
     * @template T
     * @param array<array-key, T> $rows
     * @throws InvalidStructure
     */
    public static function rows(array $rows, Dialect $dialect): void
    {
        if (!array_is_list($rows) || $rows === []) {
            throw new InvalidStructure('Row input requires a nonempty ordered list.');
        }
        $width = null;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new InvalidStructure('Each row must contain an ordered expression list.');
            }
            Collections::objects($row, Expression::class);
            if ($width !== null && count($row) !== $width || $row === [] && $dialect !== Dialect::MySql) {
                throw new InvalidStructure('Rows must have equal widths and use a valid row arity.');
            }
            $width = count($row);
            foreach ($row as $value) {
                if (!$value instanceof Expression || $value->type->dialect !== $dialect) {
                    throw new InvalidStructure('Row values must use the statement dialect.');
                }
            }
        }
    }

    /**
     * An unresolved wildcard represents an unknown number of columns.
     */
    public static function width(ResultStatement $query): ?int
    {
        foreach ($query->resultColumns() as $output) {
            if ($output->expression instanceof \SqlSemantics\Model\Scalar\Reference\Wildcard) {
                return null;
            }
        }
        return count($query->resultColumns());
    }

    /**
     * @throws InvalidStructure
     */
    public static function insertion(Insertion $insertion, ?int $width, Dialect $dialect): void
    {
        if ($width === null || $width === 0 && $dialect === Dialect::MySql || !$insertion->explicitColumns && !$insertion->target->declaration->resolved) {
            return;
        }
        if (count($insertion->columns) !== $width) {
            throw new InvalidStructure('Each input position requires its declared insertion destination.');
        }
    }

    /**
     * @param list<list<Expression|\SqlSemantics\Model\Write\DefaultSource>> $rows
     * @throws InvalidStructure
     */
    public static function writes(array $rows, Dialect $dialect): void
    {
        $rows = Collections::nonEmpty($rows);
        $width = count($rows[0]);
        foreach ($rows as $row) {
            new \SqlSemantics\Model\Write\InputRow($dialect, $row);
            if (count($row) !== $width) {
                throw new InvalidStructure('Write rows must have equal widths.');
            }
        }
    }
}
