<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Show;

use MySqlMemory\Dictionary\Check;
use MySqlMemory\Dictionary\ForeignKey;
use MySqlMemory\Dictionary\TableDefinition;

/**
 * Writes the foreign keys and CHECK constraints of a table as SHOW CREATE TABLE writes them.
 *
 * The foreign keys come first, then the CHECK constraints, each in the order of their names. A
 * foreign key names its referenced table with its database when that is not the database of the
 * table, and its actions but NO ACTION; a CHECK constraint that is not enforced says so in a
 * versioned comment (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-create-table.html.
 *
 * @visibility MySqlMemory
 */
final class ConstraintText
{
    /**
     * @param bool $legacy Whether the text is that of MySQL 5.6 or 5.7, which writes no RESTRICT
     */
    public function __construct(public readonly bool $legacy = false)
    {
    }

    /**
     * Answers the lines of the constraints of a table.
     *
     * @return list<string>
     */
    public function lines(TableDefinition $table): array
    {
        $keys = $table->foreignKeys;
        usort($keys, static fn (ForeignKey $left, ForeignKey $right): int => strcmp($left->name, $right->name));
        $lines = array_map(fn (ForeignKey $key): string => $key->text($table, $this->legacy), $keys);
        foreach ($table->checks as $check) {
            $lines[] = $this->check($check);
        }

        return $lines;
    }

    /**
     * Writes a CHECK constraint.
     */
    public function check(Check $check): string
    {
        return 'CONSTRAINT ' . $this->name($check->name) . ' CHECK (' . $check->text . ')' . ($check->enforced ? '' : ' /*!80016 NOT ENFORCED */');
    }

    /**
     * Quotes an identifier between backticks, a backtick doubled.
     */
    public function name(string $name): string
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }
}
