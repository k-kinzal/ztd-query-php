<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Shape\RowShape;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

/**
 * The parenthesized parameter list of a stored procedure or function.
 *
 * Rule: MYSQL-ROUTINE-PARAMETERS-001. Inside the routine body every
 * parameter, whatever its direction, is a variable: the list is the
 * relation whose row holds one position per parameter with the declared
 * type, and a name used as a value in the body resolves to that position
 * unless a local variable hides it. A parameter can be NULL. Terminates:
 * one pass over the list.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-procedure.html,
 * https://dev.mysql.com/doc/refman/8.4/en/local-variable-scope.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Counting the parameters of an empty list
 *     count((new \SqlSemantics\Platform\MySql\Statement\Routine\ParameterList([]))->parameters) // => 0
 */
final class ParameterList implements Relation
{
    use Snapshot;

    /**
     * @var list<Parameter> The parameters in order
     */
    public readonly array $parameters;

    /**
     * @param list<Parameter> $parameters The parameters in order
     */
    public function __construct(array $parameters)
    {
        $this->parameters = Check::listOf($parameters, Parameter::class, 'A parameter list holds parameters.');
    }

    /**
     * Answers the row of the parameters.
     */
    public function deriveRelation(Derivation $derivation, Environment $environment): RelationFact
    {
        $slots = [];
        foreach ($this->parameters as $parameter) {
            $slots[] = new OutputSlot($parameter->name, new Known($parameter->type), Nullability::Nullable);
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
