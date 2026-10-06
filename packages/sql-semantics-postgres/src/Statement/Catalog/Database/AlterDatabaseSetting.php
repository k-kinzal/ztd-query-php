<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Database;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to set or reset the default of a configuration parameter for a database.
 *
 * Rule: PG-ALTERDB-004. Mirrors `AlterDatabaseSetStmt`: a database name and
 * the SET or RESET request of the utility family, which is derived as the
 * request it is. Source: https://www.postgresql.org/docs/17/sql-alterdatabase.html. Status: Implemented.
 *
 * @visibility public
 * @example Telling that the request is a statement of its own
 *     is_subclass_of(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Database\AlterDatabaseSetting::class, \SqlSemantics\Statement\Statement::class) // => true
 */
final class AlterDatabaseSetting implements Statement
{
    use Snapshot;

    /**
     * @param Name $name The database name
     * @param Statement $setting The SET or RESET request
     */
    public function __construct(public readonly Name $name, public readonly Statement $setting)
    {
    }

    /**
     * Derives the SET or RESET request.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $derivation->statement($this->setting);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER', 'DATABASE')->name($this->name, NameUse::Column)->node($this->setting);
    }
}
