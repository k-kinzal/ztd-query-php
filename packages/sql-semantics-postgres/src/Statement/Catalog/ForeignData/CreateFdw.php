<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\ForeignData;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Statement;
use SqlSemantics\Platform\PostgreSql\Rules\Catalog\ForeignOptions;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Language\FunctionClause;
use SqlSemantics\Platform\PostgreSql\Statement\Option\GenericOption;

/**
 * A request to define a foreign-data wrapper.
 *
 * Rule: PG-FDW-001. Mirrors `CreateFdwStmt`: name, handler and validator
 * clauses in the order written, and options. A handler or validator named
 * twice is a diagnostic (PG-FOREIGN-OPTION-001).
 * Source: https://www.postgresql.org/docs/17/sql-createforeigndatawrapper.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class CreateFdw implements Statement
{
    use Snapshot;

    /**
     * @var list<FunctionClause> The handler and validator clauses in the order written
     */
    public readonly array $functions;

    /**
     * @var list<GenericOption> The options
     */
    public readonly array $options;

    /**
     * @param Name $name The wrapper name
     * @param list<FunctionClause> $functions The handler and validator clauses in the order written
     * @param list<GenericOption> $options The options
     */
    public function __construct(public readonly Name $name, array $functions = [], array $options = [])
    {
        $this->functions = Check::listOf($functions, FunctionClause::class, 'Wrapper functions are a list of function clauses.');
        $this->options = Check::listOf($options, GenericOption::class, 'Wrapper options are a list of options.');
    }

    /**
     * Reports a function role named twice.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new ForeignOptions())->functions($derivation, $this->functions);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('CREATE', 'FOREIGN', 'DATA', 'WRAPPER')->name($this->name, NameUse::Column);
        foreach ($this->functions as $function) {
            $out->node($function);
        }
        (new ForeignOptions())->write($out, $this->options);
    }
}
