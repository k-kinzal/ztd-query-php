<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Loading;

use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * The LINES clause of a load: the row terminator (for XML, the row element name) and the prefix skipped before each row.
 * @visibility public
 * @example Reading the row separators
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $lines = (new \SqlSemantics\Binder($schema))->bind("LOAD DATA INFILE 'rows.txt' INTO TABLE t LINES STARTING BY '>' TERMINATED BY ';'")->layout->lines;
 *     [$lines->terminator?->text, $lines->start?->text] // => ["';'", "'>'"]
 */
final class LineLayout
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly ?Literal $terminator = null, public readonly ?Literal $start = null)
    {
        foreach ([$terminator, $start] as $separator) {
            if ($separator !== null) {
                SeparatorText::bytes($separator);
            }
        }
    }
}
