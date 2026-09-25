<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Recursion;

use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * PostgreSQL CYCLE columns SET mark [TO value DEFAULT value] USING path: a recursive query stops following a row whose
 * listed columns repeat an earlier row on its path, and adds a mark column (TRUE/FALSE unless both values are written)
 * and a path column.
 * @visibility public
 * @example Reading the cycle clause of a recursive query
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build());
 *     $statement = $binder->bind('WITH RECURSIVE r(n) AS (SELECT 1 UNION ALL SELECT n % 3 + 1 FROM r) CYCLE n SET looped USING path SELECT n FROM r');
 *     $statement->ctes->definitions[0]->cycle->markColumn // => 'looped'
 *     $statement->ctes->definitions[0]->cycle->markValue // => null
 */
final class CycleClause
{
    /**
     * @var non-empty-list<string> CTE columns whose repetition is a cycle
     */
    public readonly array $columns;

    /**
     * @param list<string> $columns
     * @param Expression|null $markValue Constant marking a cycle; null with $markDefault null means TRUE
     * @param Expression|null $markDefault Constant marking any other row; null with $markValue null means FALSE
     * @throws InvalidStructure
     */
    public function __construct(
        array $columns,
        public readonly string $markColumn,
        public readonly ?Expression $markValue,
        public readonly ?Expression $markDefault,
        public readonly string $pathColumn,
    ) {
        Collections::strings($columns);
        $this->columns = Collections::nonEmpty($columns);
        if ($markColumn === '' || $pathColumn === '') {
            throw new InvalidStructure('A CYCLE clause names its mark and path columns.');
        }
        if (($markValue === null) !== ($markDefault === null)) {
            throw new InvalidStructure('A CYCLE clause writes both mark values or neither.');
        }
    }
}
