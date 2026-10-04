<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Routine;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Shape\RowShape;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Nullability;

/**
 * The parenthesized parameter list of a routine definition.
 *
 * In an SQL-standard body the input parameters are visible as one row: a
 * name that is no column of the statement's own relations denotes the
 * parameter of that name, and `routine_name.parameter` qualifies it. The list
 * is therefore a relation whose row shape holds one slot per input
 * parameter, named when the parameter is named; a parameter can be NULL.
 * Source: https://www.postgresql.org/docs/17/xfunc-sql.html#XFUNC-SQL-FUNCTION-ARGUMENTS.
 *
 * @visibility public
 * @example Counting the parameters of an empty list
 *     count((new \SqlSemantics\Platform\PostgreSql\Statement\Routine\RoutineParameters([]))->parameters) // => 0
 */
final class RoutineParameters implements Relation
{
    use Snapshot;

    /**
     * @var list<FunctionParameter> The parameters in order
     */
    public readonly array $parameters;

    /**
     * @param list<FunctionParameter> $parameters The parameters in order
     */
    public function __construct(array $parameters)
    {
        $this->parameters = Check::listOf($parameters, FunctionParameter::class, 'A parameter list holds function parameters.');
    }

    /**
     * Derives the types and default values, and answers the row of the input parameters.
     */
    public function deriveRelation(Derivation $derivation, Environment $environment): RelationFact
    {
        $slots = [];
        foreach ($this->parameters as $parameter) {
            $parameter->deriveClause($derivation, $environment);
            if ($parameter->input()) {
                $slots[] = new OutputSlot($parameter->name, $parameter->type->typeFact($derivation->context), Nullability::Nullable);
            }
        }

        return new RelationFact(new RowShape($slots));
    }

    /**
     * Writes the parameters in parentheses.
     */
    public function render(Output $out): void
    {
        $out->symbol('(')->list($this->parameters)->symbol(')');
    }
}
