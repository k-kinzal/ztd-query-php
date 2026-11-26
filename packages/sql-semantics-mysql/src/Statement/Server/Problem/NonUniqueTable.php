<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Problem;

use SqlSemantics\Platform\MySql\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A table or alias that a table list of a server administration statement names twice in one database.
 *
 * The server rejects the statement with ER_NONUNIQ_TABLE while it parses
 * the list.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/lock-tables.html,
 * https://dev.mysql.com/doc/refman/8.4/en/analyze-table.html.
 *
 * @visibility SqlSemantics
 * @example Describing the problem
 *     (new \SqlSemantics\Platform\MySql\Statement\Server\Problem\NonUniqueTable(new \SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name('t')))->message() // => "Not unique table/alias: 't'."
 */
final class NonUniqueTable implements Diagnostic
{
    use Snapshot;

    /**
     * @param Name $alias The alias, or the table name when no alias is written, at its later occurrence
     */
    public function __construct(public readonly Name $alias)
    {
    }

    /**
     * Describes the problem.
     */
    public function message(): string
    {
        return "Not unique table/alias: '" . $this->alias->value . "'.";
    }
}
