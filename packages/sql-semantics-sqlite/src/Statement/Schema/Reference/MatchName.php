<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Reference;

use SqlSemantics\Platform\Sqlite\Model\Contract\NameUse;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * A MATCH clause of a foreign key.
 *
 * Rule: SQLITE-FK-MATCH-001. "SQLite parses MATCH clauses (i.e. does not
 * report a syntax error if you specify one), but does not enforce them. All
 * foreign key constraints in SQLite are handled as if MATCH SIMPLE were
 * specified." Any name is accepted and kept.
 * Source: https://sqlite.org/foreignkeys.html#fk_unsupported. Status: Implemented.
 *
 * @visibility SqlSemantics
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
