<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Utility\MaintenanceFacts;
use SqlSemantics\Platform\PostgreSql\Rules\Utility\OptionRules;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `ANALYZE`: a request to collect statistics about tables.
 *
 * Rule: PG-ANALYZE-001. Mirrors PostgreSQL's `VacuumStmt` without
 * `is_vacuumcmd`: the options in the order written and the tables with
 * their columns, where no table stands for every table of the database.
 * ANALYSE is the British spelling of the same command and is kept. The older
 * syntax writes VERBOSE as a word. Facts: each table is resolved, and a
 * listed column is looked up in a declared table. Diagnostics: an option
 * ANALYZE does not know; a column the declared table does not have.
 * Source: https://www.postgresql.org/docs/17/sql-analyze.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading an ANALYZE in the British spelling
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ANALYSE VERBOSE t');
 *     [$operation->statement->keyword, $operation->toString()] // => [\SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\OptionKeyword::Analyse, 'ANALYSE VERBOSE t']
 * @example Refusing a command keyword that is not a spelling of ANALYZE
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\Analyze(\SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\OptionKeyword::Format) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class Analyze implements Statement
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
     * @param OptionKeyword $keyword The spelling of the command: ANALYZE or ANALYSE
     * @param list<UtilityOption> $options The options in the order written
     * @param OptionSyntax $syntax How the options are written
     * @param list<MaintenanceTarget> $targets The tables; none stands for every table
     */
    public function __construct(public readonly OptionKeyword $keyword = OptionKeyword::Analyze, array $options = [], public readonly OptionSyntax $syntax = OptionSyntax::Words, array $targets = [])
    {
        Check::input($keyword !== OptionKeyword::Format, 'The command is spelled ANALYZE or ANALYSE.');
        $this->options = Check::listOf($options, UtilityOption::class, 'The options of ANALYZE are utility options.');
        $this->targets = Check::listOf($targets, MaintenanceTarget::class, 'The tables of ANALYZE are maintenance targets.');
        Check::input((new OptionRules())->writable($this->options, $syntax, ['verbose']), 'The options cannot be written in that syntax.');
    }

    /**
     * Resolves the tables and columns and reports what ANALYZE rejects.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new OptionRules())->derive($derivation, 'ANALYZE', $this->options);
        (new MaintenanceFacts())->targets($derivation, $this->targets);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->keyword->value);
        (new OptionRules())->write($out, $this->options, $this->syntax);
        $out->list($this->targets);
    }
}
