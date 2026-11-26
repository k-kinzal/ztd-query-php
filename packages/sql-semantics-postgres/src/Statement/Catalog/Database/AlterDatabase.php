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
use SqlSemantics\Platform\PostgreSql\Rules\Catalog\ClauseFacts;
use SqlSemantics\Platform\PostgreSql\Rules\Catalog\OptionChecks;

/**
 * A request to change options of a database.
 *
 * Rule: PG-ALTERDB-001. Mirrors `AlterDatabaseStmt` with an option list. The
 * optional WITH is not kept. ALTER DATABASE recognizes fewer options than
 * CREATE DATABASE, and TABLESPACE only alone (PG-CATALOG-OPTION-001).
 * Source: https://www.postgresql.org/docs/17/sql-alterdatabase.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class AlterDatabase implements Statement
{
    use Snapshot;

    /**
     * @var list<DatabaseOption> The options in the order written
     */
    public readonly array $options;

    /**
     * @param Name $name The database name
     * @param list<DatabaseOption> $options The options in the order written
     */
    public function __construct(public readonly Name $name, array $options = [])
    {
        $this->options = Check::listOf($options, DatabaseOption::class, 'Database options are a list of options.');
    }

    /**
     * Derives the option values and checks the option names.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new ClauseFacts())->derive($derivation, $this->options);
        (new OptionChecks())->database($derivation, $this->options, true);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER', 'DATABASE')->name($this->name, NameUse::Column)->keyword('WITH');
        foreach ($this->options as $option) {
            $out->node($option);
        }
    }
}
