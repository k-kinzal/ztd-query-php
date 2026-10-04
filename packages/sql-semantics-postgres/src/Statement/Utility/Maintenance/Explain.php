<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Utility\ExplainFacts;
use SqlSemantics\Platform\PostgreSql\Rules\Utility\OptionRules;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `EXPLAIN`: a request for the execution plan of a statement.
 *
 * Rule: PG-EXPLAIN-001. Mirrors PostgreSQL's `ExplainStmt` (query,
 * options). The older syntax writes ANALYZE and VERBOSE as words in that
 * order. Facts: the explained statement is derived as an inspected request,
 * so every part of it has its facts and diagnostics, but its rows and
 * declarations are not those of EXPLAIN. EXPLAIN returns one column named
 * `QUERY PLAN`, never NULL, whose type follows the last FORMAT option: `xml`
 * for XML, `json` for JSON, `text` otherwise. Diagnostics: an option EXPLAIN
 * does not know in the release; a FORMAT value that is none of TEXT, XML,
 * JSON, YAML. Termination: the statement is derived once.
 * Source: https://www.postgresql.org/docs/17/sql-explain.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the row EXPLAIN returns
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('EXPLAIN (ANALYZE, FORMAT JSON) SELECT 1');
 *     [$operation->field(0)->name->value, $operation->field(0)->type->descriptor->name(), $operation->toString()] // => ['QUERY PLAN', 'json', 'EXPLAIN (ANALYZE, FORMAT json) SELECT 1']
 * @example Refusing words the old syntax does not have
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\Explain(
 *         new \SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\Checkpoint(),
 *         [new \SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\UtilityOption(new \SqlSemantics\Statement\Identifier\Name('costs'))],
 *     ) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class Explain implements Statement
{
    use Snapshot;

    /**
     * @var list<UtilityOption> The options in the order written
     */
    public readonly array $options;

    /**
     * @param Statement $statement The explained statement
     * @param list<UtilityOption> $options The options in the order written
     * @param OptionSyntax $syntax How the options are written
     */
    public function __construct(public readonly Statement $statement, array $options = [], public readonly OptionSyntax $syntax = OptionSyntax::Words)
    {
        $this->options = Check::listOf($options, UtilityOption::class, 'The options of EXPLAIN are utility options.');
        Check::input((new OptionRules())->writable($this->options, $syntax, ['analyze', 'verbose']), 'The options cannot be written in that syntax.');
    }

    /**
     * Derives the explained statement as an inspected request and records the plan row.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new OptionRules())->derive($derivation, 'EXPLAIN', $this->options);
        $derivation->inspected($this->statement);
        $derivation->output((new ExplainFacts())->rows($derivation, $this->options));
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('EXPLAIN');
        (new OptionRules())->write($out, $this->options, $this->syntax);
        $out->node($this->statement);
    }
}
