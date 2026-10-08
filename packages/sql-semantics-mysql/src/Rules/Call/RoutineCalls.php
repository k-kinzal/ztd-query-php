<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Call;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\NamedArgument;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\ReservedFunction;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\WrongArgumentCount;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Missing\UndeclaredRoutine;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

/**
 * The result of a call written as `name(...)` or `db.name(...)`: a native, loadable or stored function.
 *
 * Rule: MYSQL-ROUTINE-CALL-001. A qualified call is a stored function of
 * that database: when the session holds the type the function returns
 * (Settings::$functions), the call has that type and can be NULL, else its
 * result depends on the undeclared routine. An unqualified call of a native
 * function of the release (MYSQL-NATIVE-FUNCTIONS-001) has the documented
 * result of that function (MYSQL-CALL-RESULT-001); an argument alias, a
 * wrong argument count and a function reserved for the data dictionary are
 * errors the server reports, and the result is invalid; a reserved function
 * is refused whatever its arguments (verified on a live 8.4 server). Any other
 * unqualified call is a loadable function or a stored function of the
 * default database, typed the same way.
 * Terminates: one table lookup over the derived argument facts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/function-resolution.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class RoutineCalls
{
    /**
     * Answers the facts of a call from the facts of its arguments and reports its problems.
     *
     * @param list<ScalarFact> $arguments
     */
    public function result(FunctionCall $call, array $arguments, Derivation $derivation): ScalarFact
    {
        $release = $derivation->context->profile->grammar;
        $natives = new NativeFunctions();
        if ($call->schema !== null || !$natives->exists($release, $call->name->value)) {
            $returns = Settings::of($derivation->context)->function(($call->schema ?? $derivation->context->searchPath[0])->value, $call->name->value);

            return $returns === null || $call->named()
                ? new ScalarFact(new Dependent([new UndeclaredRoutine(new QualifiedName($call->name, $call->schema))]), Nullability::Dependent)
                : new ScalarFact(new Known($returns), Nullability::Nullable);
        }
        $row = $natives->row($release, $call->name->value, count($arguments));
        $reserved = in_array(true, array_column($natives->rows($release, $call->name->value), 3), true);
        if ($row === null || $reserved || $call->named()) {
            $problem = match (true) {
                $reserved => new ReservedFunction($call->name),
                $call->named() => new NamedArgument($call->name),
                $row === null => new WrongArgumentCount($call->name, count($arguments)),
                default => new ReservedFunction($call->name),
            };
            $derivation->report($problem);

            return new ScalarFact(new Invalid($problem), Nullability::Dependent);
        }

        return (new ResultTyping())->fact($row[2], $arguments);
    }
}
