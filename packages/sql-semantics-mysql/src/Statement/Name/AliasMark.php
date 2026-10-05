<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Name;

use SqlSemantics\Rendering\Output;

/**
 * What is written between an operand and its alias: `AS`, nothing, or in MySQL 5.6 and 5.7 `=` before a table alias.
 *
 * The three forms give the same alias. The form is kept because MySQL names
 * an unaliased select list expression after its text, and an alias written
 * inside that expression, in a subquery for example, is part of the text.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/select.html,
 * sql/sql_yacc.yy of MySQL 5.7 (`table_alias`).
 *
 * @visibility public
 * @example Reading how an alias is written
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a total FROM t');
 *     $query->statement->items[0]->mark // => \SqlSemantics\Platform\MySql\Statement\Name\AliasMark::Bare
 */
enum AliasMark
{
    case As;
    case Bare;
    case Equals;

    /**
     * Writes what precedes the alias.
     *
     * @example Writing the keyword of an alias
     *     $out = new \SqlSemantics\Rendering\Output(new \SqlSemantics\Platform\MySql\Rendering\Codec(\SqlSemantics\Contract\GrammarRelease::MySql847));
     *     \SqlSemantics\Platform\MySql\Statement\Name\AliasMark::As->write($out);
     *     $out->pieces()[0]->text // => 'AS'
     */
    public function write(Output $out): void
    {
        match ($this) {
            self::As => $out->keyword('AS'),
            self::Bare => $out,
            self::Equals => $out->symbol('='),
        };
    }
}
