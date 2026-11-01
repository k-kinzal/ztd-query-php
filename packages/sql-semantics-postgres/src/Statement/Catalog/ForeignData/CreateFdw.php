<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\ForeignData;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Catalog\ForeignOptions;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Language\FunctionClause;
use SqlSemantics\Platform\PostgreSql\Statement\Option\GenericOption;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Statement;

/**
 * A request to define a foreign-data wrapper.
 *
 * Rule: PG-FDW-001. Mirrors `CreateFdwStmt`: name, handler and validator
 * clauses in the order written, and options. A handler or validator named
 * twice is a diagnostic (PG-FOREIGN-OPTION-001).
 * Source: https://www.postgresql.org/docs/17/sql-createforeigndatawrapper.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the handler of a wrapper
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze("CREATE FOREIGN DATA WRAPPER w HANDLER h NO VALIDATOR OPTIONS (debug 'true')");
 *     [$operation->functions[0]->function?->last()->value, $operation->toString()] // => ['h', "CREATE FOREIGN DATA WRAPPER w HANDLER h NO VALIDATOR OPTIONS (debug 'true')"]
 */
final class CreateFdw extends \SqlSemantics\Statement\SqlStatement implements Statement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

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
    public function __construct(public readonly Name $name, array $functions = [], array $options = [], public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null, ?\SqlSemantics\Statement\StatementSemantics $statementInformation = null)
    {
        if ($statementInformation !== null) {
            $this->initializeStatementInformation($statementInformation);
        }
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
