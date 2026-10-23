<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Hint\Form;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Snapshot;

/**
 * A table an optimizer hint names: its name or alias, and the query block it is in when the hint writes one (`t@qb`).
 *
 * The name is matched against the table names and aliases of the query
 * block as written, and cannot be qualified with a database. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/optimizer-hints.html.
 *
 * @visibility public
 * @example Writing a table of a hint
 *     (new \SqlSemantics\Platform\MySql\Statement\Hint\Form\HintTable('t1', 'qb'))->text() // => '`t1`@`qb`'
 */
final class HintTable
{
    use Snapshot;

    /**
     * @param string $name The table name or alias, as written
     * @param string|null $block The query block the hint writes after `@`, or null for the block of the hint
     */
    public function __construct(public readonly string $name, public readonly ?string $block = null)
    {
        Check::input($name !== '' && $block !== '', 'A table and a query block of a hint have a name.');
    }

    /**
     * Answers the table as a hint writes it, each name quoted.
     */
    public function text(): string
    {
        return self::quote($this->name) . ($this->block === null ? '' : '@' . self::quote($this->block));
    }

    /**
     * Answers a name of a hint between backticks, each backtick doubled.
     *
     * @example Quoting a name
     *     \SqlSemantics\Platform\MySql\Statement\Hint\Form\HintTable::quote('a`b') // => '`a``b`'
     */
    public static function quote(string $name): string
    {
        return '`' . str_replace('`', '``', $name) . '`';
    }
}
