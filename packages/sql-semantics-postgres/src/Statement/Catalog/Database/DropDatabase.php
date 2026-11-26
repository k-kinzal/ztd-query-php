<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Database;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Statement;

/**
 * A request to remove a database.
 *
 * Rule: PG-DROPDB-001. Mirrors `DropdbStmt`. The options are kept in the
 * order written, repetitions included; the optional WITH before them is not kept.
 * Source: https://www.postgresql.org/docs/17/sql-dropdatabase.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class DropDatabase implements Statement
{
    use Snapshot;

    /**
     * @var list<DropDatabaseOption> The options in the order written
     */
    public readonly array $options;

    /**
     * @param Name $name The database name
     * @param bool $ifExists Whether IF EXISTS is written
     * @param list<DropDatabaseOption> $options The options in the order written
     */
    public function __construct(public readonly Name $name, public readonly bool $ifExists = false, array $options = [])
    {
        $this->options = Check::listOf($options, DropDatabaseOption::class, 'Drop options are a list of options.');
    }

    /**
     * Derives nothing: a database is not part of a declaration context.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('DROP', 'DATABASE');
        if ($this->ifExists) {
            $out->keyword('IF', 'EXISTS');
        }
        $out->name($this->name, NameUse::Column);
        if ($this->options !== []) {
            $out->keyword('WITH')->symbol('(');
            foreach ($this->options as $position => $option) {
                if ($position > 0) {
                    $out->symbol(',');
                }
                $out->keyword($option->value);
            }
            $out->symbol(')');
        }
    }
}
