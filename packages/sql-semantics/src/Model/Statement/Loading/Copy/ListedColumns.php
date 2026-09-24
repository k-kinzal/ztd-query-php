<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Loading\Copy;

use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Applies a COPY FORCE option to the listed columns.
 * @visibility public
 * @example Reading the listed columns
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('COPY t FROM STDIN CSV FORCE NOT NULL a, b', strict: false);
 *     $statement->options->forceNotNull->columns // => ['a', 'b']
 */
final class ListedColumns implements ColumnChoice
{
    /**
     * @param non-empty-list<string> $columns Column names in written order
     * @throws InvalidStructure
     */
    public function __construct(public readonly array $columns)
    {
        Collections::nonEmpty($columns);
        if (in_array('', $columns, true)) {
            throw new InvalidStructure('A forced column requires a nonempty name.');
        }
    }
}
