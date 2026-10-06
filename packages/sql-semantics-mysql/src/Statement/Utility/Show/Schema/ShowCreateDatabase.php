<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\MySql\Rules\Utility\Report;
use SqlSemantics\Platform\MySql\Rules\Utility\ShowFacts;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * SHOW CREATE DATABASE (SHOW CREATE SCHEMA): the statement that creates a database.
 *
 * Rule: MYSQL-SHOW-CREATE-DATABASE-001. IF NOT EXISTS makes the returned statement carry the same
 * clause. The columns are those of the layout of MYSQL-SHOW-ROWS-001.
 * Terminates: a fixed layout.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-create-database.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the statement
 *     $show = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SHOW CREATE SCHEMA IF NOT EXISTS shop');
 *     [$show->field(1)->name?->value, $show->toString()] // => ['Create Database', 'SHOW CREATE DATABASE IF NOT EXISTS shop']
 */
final class ShowCreateDatabase implements Statement
{
    use Snapshot;

    /**
     * @param Name $name The database
     * @param bool $ifNotExists Whether IF NOT EXISTS is written
     */
    public function __construct(public readonly Name $name, public readonly bool $ifNotExists = false)
    {
    }

    /**
     * Derives the rows.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new ShowFacts())->rows($derivation, Report::CreateDatabase);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('SHOW', 'CREATE', 'DATABASE');
        if ($this->ifNotExists) {
            $out->keyword('IF', 'NOT', 'EXISTS');
        }
        $out->name($this->name, NameUse::Qualifier);
    }
}
