<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\ForeignData;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Catalog\ForeignOptions;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Language\FunctionClause;
use SqlSemantics\Platform\PostgreSql\Statement\Option\AlteredOption;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to change the functions or options of a foreign-data wrapper.
 *
 * Rule: PG-FDW-002. Mirrors `AlterFdwStmt`. At least one function clause or
 * option change is written. NO HANDLER and NO VALIDATOR remove the function.
 * Source: https://www.postgresql.org/docs/17/sql-alterforeigndatawrapper.html. Status: Implemented.
 *
 * @visibility public
 * @example Removing the validator
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER FOREIGN DATA WRAPPER w NO VALIDATOR');
 *     $operation->statement->functions[0]->function // => null
 */
final class AlterFdw implements Statement
{
    use Snapshot;

    /**
     * @var list<FunctionClause> The handler and validator clauses in the order written
     */
    public readonly array $functions;

    /**
     * @var list<AlteredOption> The option changes
     */
    public readonly array $options;

    /**
     * @param Name $name The wrapper name
     * @param list<FunctionClause> $functions The handler and validator clauses in the order written
     * @param list<AlteredOption> $options The option changes
     */
    public function __construct(public readonly Name $name, array $functions = [], array $options = [])
    {
        $this->functions = Check::listOf($functions, FunctionClause::class, 'Wrapper functions are a list of function clauses.');
        $this->options = Check::listOf($options, AlteredOption::class, 'Wrapper option changes are a list of changes.');
        Check::input($this->functions !== [] || $this->options !== [], 'ALTER FOREIGN DATA WRAPPER changes a function or an option.');
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
        $out->keyword('ALTER', 'FOREIGN', 'DATA', 'WRAPPER')->name($this->name, NameUse::Column);
        foreach ($this->functions as $function) {
            $out->node($function);
        }
        (new ForeignOptions())->write($out, $this->options);
    }
}
