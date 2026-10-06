<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Routine;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Routine\RoutineFacts;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Option\RoutineDefinition;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Option\RoutineLanguage;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Option\RoutineOption;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `CREATE [OR REPLACE] FUNCTION` or `PROCEDURE`: defines a routine.
 *
 * Mirrors PostgreSQL's `CreateFunctionStmt`. The result is a type, a table of
 * columns, or, for a function with output parameters or a procedure, not
 * written. The body is either an option (`AS 'definition'`) or an
 * SQL-standard body (`RETURN expression` or `BEGIN ATOMIC ... END`) whose
 * statements are analyzed with the parameters visible. A definition written
 * as a string is unanalysed text in the routine's language
 * (PG-ROUTINE-SOURCE-001): the server parses it only when the routine runs. Defining a routine
 * declares no relation.
 * Source: https://www.postgresql.org/docs/17/sql-createfunction.html,
 * https://www.postgresql.org/docs/17/sql-createprocedure.html.
 *
 * @visibility public
 * @example Reading the parameters of a function
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE FUNCTION add(a int4, b int4) RETURNS int4 RETURN a + b');
 *     [$operation->statement->name->last()->value, count($operation->statement->parameters->parameters)] // => ['add', 2]
 */
final class CreateFunction implements Statement
{
    use Snapshot;

    /**
     * @var list<RoutineOption> The options in written order
     */
    public readonly array $options;

    /**
     * @param DottedName $name The routine name
     * @param RoutineParameters $parameters The parameters
     * @param TypeName|ResultTable|null $returns The written result; a procedure has none
     * @param list<RoutineOption> $options The options in written order
     * @param ReturnStatement|AtomicBody|null $body The SQL-standard body, if written
     * @param bool $procedure Whether a procedure is defined
     * @param bool $replace Whether OR REPLACE is written
     */
    public function __construct(
        public readonly DottedName $name,
        public readonly RoutineParameters $parameters,
        public readonly TypeName|ResultTable|null $returns,
        array $options = [],
        public readonly ReturnStatement|AtomicBody|null $body = null,
        public readonly bool $procedure = false,
        public readonly bool $replace = false,
    ) {
        $this->options = Check::listOf($options, RoutineOption::class, 'Routine options are a list of routine options.');
        Check::input(!$procedure || $returns === null, 'A procedure has no result.');
        $language = null;
        foreach ($this->options as $option) {
            $language ??= $option instanceof RoutineLanguage ? $option->name() : null;
        }
        foreach ($this->options as $option) {
            Check::input(!$option instanceof RoutineDefinition || $option->source->language?->value === $language, 'A routine definition is written in the language of the first LANGUAGE option, or in none without one.');
        }
    }

    /**
     * Derives the parameters, the result, the options and the body.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new RoutineFacts())->create($this, $derivation);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('CREATE');
        if ($this->replace) {
            $out->keyword('OR', 'REPLACE');
        }
        $out->keyword($this->procedure ? 'PROCEDURE' : 'FUNCTION')->node($this->name)->node($this->parameters);
        if ($this->returns !== null) {
            $out->keyword('RETURNS')->node($this->returns);
        }
        foreach ($this->options as $option) {
            $out->node($option);
        }
        $out->node($this->body);
    }
}
