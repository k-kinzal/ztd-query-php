<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Utility\MaintenanceFacts;
use SqlSemantics\Platform\PostgreSql\Rules\Utility\OptionRules;
use SqlSemantics\Platform\PostgreSql\Rules\Utility\VacuumChecks;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `VACUUM`: a request to reclaim storage and optionally analyze tables.
 *
 * Rule: PG-VACUUM-001. Mirrors PostgreSQL's `VacuumStmt` with
 * `is_vacuumcmd`: the options in the order written and the tables, where no
 * table stands for every table of the database. The older syntax writes
 * FULL, FREEZE, VERBOSE and ANALYZE as words in that order. Facts: each
 * table is resolved, and a listed column is looked up in a declared table.
 * Diagnostics: an option VACUUM does not know; a value the option cannot
 * take; the combinations of options PG-VACUUM-OPTIONS-001 lists, among them
 * a column list without ANALYZE; a column the declared table does not have.
 * Source: https://www.postgresql.org/docs/17/sql-vacuum.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading a VACUUM written with words
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('VACUUM FULL ANALYZE t');
 *     [$operation->statement->options[1]->option(), $operation->statement->targets[0]->table->name->value, $operation->toString()] // => ['analyze', 't', 'VACUUM FULL ANALYZE t']
 * @example Refusing words in another order than the grammar reads
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\Vacuum([
 *         new \SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\UtilityOption(new \SqlSemantics\Statement\Identifier\Name('verbose')),
 *         new \SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\UtilityOption(new \SqlSemantics\Statement\Identifier\Name('full')),
 *     ], \SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\OptionSyntax::Words) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class Vacuum implements Statement
{
    use Snapshot;

    /**
     * @var list<UtilityOption> The options in the order written
     */
    public readonly array $options;

    /**
     * @var list<MaintenanceTarget> The tables; none stands for every table
     */
    public readonly array $targets;

    /**
     * @param list<UtilityOption> $options The options in the order written
     * @param OptionSyntax $syntax How the options are written
     * @param list<MaintenanceTarget> $targets The tables; none stands for every table
     */
    public function __construct(array $options = [], public readonly OptionSyntax $syntax = OptionSyntax::Words, array $targets = [])
    {
        $this->options = Check::listOf($options, UtilityOption::class, 'The options of VACUUM are utility options.');
        $this->targets = Check::listOf($targets, MaintenanceTarget::class, 'The tables of VACUUM are maintenance targets.');
        Check::input((new OptionRules())->writable($this->options, $syntax, ['full', 'freeze', 'verbose', 'analyze']), 'The options cannot be written in that syntax.');
    }

    /**
     * Resolves the tables and columns and reports what VACUUM rejects.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new OptionRules())->derive($derivation, 'VACUUM', $this->options);
        $columns = (new MaintenanceFacts())->targets($derivation, $this->targets);
        (new VacuumChecks())->check($derivation, $this->options, $this->targets !== [], $columns);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('VACUUM');
        (new OptionRules())->write($out, $this->options, $this->syntax);
        $out->list($this->targets);
    }
}
