<?php

declare(strict_types=1);

namespace MySqlMemory\Dictionary;

use MySqlMemory\Evaluation\Evaluable;
use SqlSemantics\Statement\Scalar;

/**
 * A CHECK constraint of a table: its name, its condition, and whether it is enforced.
 *
 * An enforced constraint refuses a row for which its condition is false; a condition that is
 * NULL (unknown) passes. A constraint declared without a name is named after its table, as
 * `<table>_chk_<n>`, numbered among the unnamed ones in declared order.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table-check-constraints.html.
 *
 * @visibility MySqlMemory
 */
final class Check
{
    /**
     * @param string $name The constraint name
     * @param Evaluable $condition The condition, read over a row of the table
     * @param bool $enforced Whether the constraint is enforced
     * @param string $text The condition as SHOW CREATE TABLE writes it
     * @param bool $generatedName Whether the server named the constraint
     * @param list<int> $columns The positions of the columns the condition reads
     * @param Scalar|null $node The condition as written, which a change of the table declares again
     */
    public function __construct(
        public readonly string $name,
        public readonly Evaluable $condition,
        public readonly bool $enforced,
        public readonly string $text,
        public readonly bool $generatedName = false,
        public readonly array $columns = [],
        public readonly ?Scalar $node = null,
    ) {
    }
}
