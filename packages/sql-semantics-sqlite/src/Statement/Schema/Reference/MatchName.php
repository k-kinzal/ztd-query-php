<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Reference;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * A MATCH clause of a foreign key.
 *
 * Rule: SQLITE-FK-MATCH-001. "SQLite parses MATCH clauses (i.e. does not
 * report a syntax error if you specify one), but does not enforce them. All
 * foreign key constraints in SQLite are handled as if MATCH SIMPLE were
 * specified." Any name is accepted and kept.
 * Source: https://sqlite.org/foreignkeys.html#fk_unsupported. Status: Implemented.
 *
 * @visibility public
 * @example Reading a MATCH name
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('CREATE TABLE c (p REFERENCES parent MATCH FULL)');
 *     $create->statement->columns[0]->constraints[0]->arguments[0]->name->value // => 'FULL'
 */
final class MatchName implements ReferenceArgument
{
    use Snapshot;

    /**
     * @param Name $name The name written after MATCH
     */
    public function __construct(public readonly Name $name)
    {
    }

    /**
     * Writes the clause.
     */
    public function render(Output $out): void
    {
        $out->keyword('MATCH')->name($this->name, NameUse::Label);
    }
}
