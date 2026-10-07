<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A partition a PARTITION clause names that the table does not have (`ER_UNKNOWN_PARTITION`, error 1735).
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/partitioning-selection.html.
 *
 * @visibility public
 * @example Selecting a partition the table lacks
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql);
 *     $table = $semantics->analyze('CREATE TABLE t (a INT) PARTITION BY HASH(a) PARTITIONS 2');
 *     $semantics->analyze('SELECT a FROM t PARTITION (p9)', [$table])->facts->diagnostics[0]->message() // => "Unknown partition 'p9' in table 't'"
 */
final class UnknownPartition implements Diagnostic
{
    use Snapshot;

    /**
     * @param string $partition The partition as written
     * @param string $table The name of the table
     */
    public function __construct(public readonly string $partition, public readonly string $table)
    {
    }

    /**
     * Describes the problem as the server does.
     */
    public function message(): string
    {
        return sprintf("Unknown partition '%s' in table '%s'", $this->partition, $this->table);
    }
}
