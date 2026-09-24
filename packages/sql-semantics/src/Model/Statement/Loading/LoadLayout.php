<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Loading;

use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * How a load reads its input: character set, field and line separators, and the number of leading rows skipped.
 * @visibility public
 * @example Reading the input layout
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $layout = (new \SqlSemantics\Binder($schema))->bind("LOAD DATA INFILE 'rows.txt' INTO TABLE t CHARACTER SET latin1 IGNORE 2 LINES")->layout;
 *     [$layout->characterSet, $layout->skippedRows] // => ['latin1', 2]
 */
final class LoadLayout
{
    /**
     * @param string|null $characterSet Character set of the input; null uses the database default
     * @param int $skippedRows Leading rows (IGNORE n LINES or ROWS) skipped before loading
     * @throws InvalidStructure
     */
    public function __construct(public readonly ?string $characterSet = null, public readonly FieldLayout $fields = new FieldLayout(), public readonly LineLayout $lines = new LineLayout(), public readonly int $skippedRows = 0)
    {
        if ($characterSet === '' || $skippedRows < 0) {
            throw new InvalidStructure('A load layout requires a nonempty character set name and a nonnegative number of skipped rows.');
        }
    }
}
