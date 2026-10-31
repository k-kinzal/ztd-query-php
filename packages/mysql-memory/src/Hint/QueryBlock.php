<?php

declare(strict_types=1);

namespace MySqlMemory\Hint;

use SqlSemantics\Platform\MySql\Statement\Hint\OptimizerHint;

/**
 * One query block of a statement as the optimizer hints see it: its number, its name, its hints and the tables it reads.
 *
 * The server numbers the query blocks of a statement `select#1`, `select#2`, ... in the order
 * their keywords are written, a common table expression at each reference to it; the statement
 * of INSERT, UPDATE and DELETE is block 1, and the first block of the query INSERT reads shares
 * it. A block holds the hints of the comment after its keyword, the hints of INSERT after those
 * of the SELECT it shares the block with. QB_NAME gives it a name (verified on a live 8.4
 * server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/optimizer-hints.html#optimizer-hints-query-block-naming.
 *
 * @visibility MySqlMemory\Hint
 */
final class QueryBlock
{
    /**
     * The name QB_NAME gave the block, or null while it has none.
     */
    public ?string $name = null;

    /**
     * @var list<OptimizerHint> The hints written in the block, in the order the server reads them
     */
    public array $hints = [];

    /**
     * @var list<array{string, list<string>}> The tables of the block, each by its alias or name with the names of its indexes
     */
    public array $tables = [];

    /**
     * @param int $number The number of the block, from 1
     * @param bool $top Whether the block is the first one of a SELECT statement, the only place MAX_EXECUTION_TIME applies
     */
    public function __construct(public readonly int $number, public readonly bool $top = false)
    {
    }

    /**
     * Answers the name the server writes for the block in a warning: its QB_NAME, or `select#N`.
     */
    public function label(): string
    {
        return $this->name ?? 'select#' . $this->number;
    }

    /**
     * Answers the indexes of a table of the block by its alias or name, compared as written, or null when the block reads no such table.
     *
     * @return list<string>|null
     */
    public function table(string $name): ?array
    {
        foreach ($this->tables as [$alias, $indexes]) {
            if ($alias === $name) {
                return $indexes;
            }
        }

        return null;
    }
}
