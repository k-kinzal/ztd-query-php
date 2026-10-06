<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Invocation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\FunctionCall;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\NamedArgument;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ImproperName;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Missing\SessionState;
use SqlSemantics\Statement\Reference\Missing\UndeclaredRoutine;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;
use SqlSemantics\Statement\Type\NullOnly;

/**
 * Types the result of a call from its name and the facts of its arguments.
 *
 * Rule: PG-CALL-RESULT-001. A context declares no routine, so a call has a
 * known result only when it certainly resolves to a function of
 * `pg_catalog` (PG-ROUTINE-MATCH-001 over PG-CALL-SIGNATURES-001): its name
 * is qualified with `pg_catalog`, or unqualified while `pg_catalog` is the
 * first schema searched for functions (`pg_temp` is never searched for
 * them). Arguments passed by name or with VARIADIC are matched by the
 * routine's declaration and are left to it. An invalid argument makes the
 * call invalid; dependent arguments make it depend on the same inputs. A
 * resolved call has the row's result type and NULL rule; a clause its kind
 * does not accept is a diagnostic and leaves the call without a type
 * (PG-CALL-CHECKS-001). Any other call depends on the undeclared routine; a
 * name with a catalog qualifier also depends on the name of the current
 * database; a name of more than three parts is improper.
 * Source: https://www.postgresql.org/docs/17/typeconv-func.html,
 * https://www.postgresql.org/docs/17/ddl-schemas.html#DDL-SCHEMAS-PATH. Termination: constant work per argument. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class CallTyping
{
    /**
     * Answers the facts of a call written with a function name.
     *
     * @param list<ScalarFact> $arguments The facts of the arguments in order
     */
    public function call(Derivation $derivation, FunctionCall $call, array $arguments): ScalarFact
    {
        $checks = new CallChecks();
        $checks->arguments($derivation, $call->arguments);
        $parts = $call->name->parts;
        $name = $call->name->last();
        $qualified = $call->name->qualified();
        if ($qualified === null) {
            $problem = new ImproperName($call->name);
            $derivation->report($problem);

            return new ScalarFact(new Invalid($problem), Nullability::Dependent);
        }
        $named = false;
        foreach ($call->arguments as $argument) {
            $named = $named || $argument instanceof NamedArgument;
        }
        $schema = count($parts) === 2 ? $parts[0]->value : null;
        $searched = count($parts) === 1 && $this->catalogFirst($derivation->context);
        if ($named || $call->variadic || $qualified->catalog !== null || (!$searched && $schema !== 'pg_catalog')) {
            $missing = $qualified->catalog !== null ? [new SessionState('the name of the current database')] : [];

            return $this->inputs($arguments) ?? new ScalarFact(new Dependent([...$missing, new UndeclaredRoutine($qualified)]), Nullability::Dependent);
        }
        $fact = $this->inputs($arguments);
        $row = $fact === null ? $this->row($name->value, $arguments, $schema !== null) : null;
        if ($row === null) {
            return $fact ?? new ScalarFact(new Dependent([new UndeclaredRoutine($qualified)]), Nullability::Dependent);
        }
        $misuse = $checks->misuse($derivation, $call, $row[1]);

        return $misuse === null ? $this->result($row, $arguments) : new ScalarFact(new Invalid($misuse), Nullability::Dependent);
    }

    /**
     * Answers the facts of a call of a `pg_catalog` function by a fixed name, as the SQL-syntax forms make.
     *
     * @param list<ScalarFact> $arguments The facts of the arguments in order
     * @param bool $catalogOnly Whether only `pg_catalog` is searched, as for the SQL-syntax forms; otherwise the name is unqualified
     */
    public function catalog(AnalysisContext $context, string $name, array $arguments, bool $catalogOnly = true): ScalarFact
    {
        $routine = new QualifiedName(new Name($name), $catalogOnly ? new Name('pg_catalog') : null);
        $fact = $this->inputs($arguments);
        if ($fact !== null) {
            return $fact;
        }
        $row = $catalogOnly || $this->catalogFirst($context) ? $this->row($name, $arguments, $catalogOnly) : null;

        return $row === null ? new ScalarFact(new Dependent([new UndeclaredRoutine($routine)]), Nullability::Dependent) : $this->result($row, $arguments);
    }

    /**
     * Answers the row a call resolves to, from the argument types.
     *
     * @param list<ScalarFact> $arguments
     *
     * @return array{string, string, string, list<string>}|null
     */
    public function row(string $name, array $arguments, bool $catalogOnly): ?array
    {
        $coercions = new Coercions();
        $keys = [];
        foreach ($arguments as $argument) {
            $key = $coercions->key($argument->type);
            if ($key === null) {
                return null;
            }
            $keys[] = $key;
        }

        return (new RoutineMatch())->find($name, $keys, $catalogOnly);
    }

    /**
     * Answers the facts of a resolved call: the row's result type and NULL rule.
     *
     * @param array{string, string, string, list<string>} $row
     * @param list<ScalarFact> $arguments
     */
    public function result(array $row, array $arguments): ScalarFact
    {
        $nullability = match ($row[2]) {
            'N' => Nullability::NotNull,
            'P' => $this->strict($arguments),
            default => Nullability::Nullable,
        };

        return new ScalarFact(new Known((new Coercions())->type($row[0])), $nullability);
    }

    /**
     * Answers the NULL fact of a strict function: NULL exactly when an argument is.
     *
     * @param list<ScalarFact> $arguments
     */
    public function strict(array $arguments): Nullability
    {
        $nullability = Nullability::NotNull;
        foreach ($arguments as $argument) {
            $nullability = $nullability->propagate($argument->type instanceof NullOnly ? Nullability::Nullable : $argument->nullability);
        }

        return $nullability;
    }

    /**
     * Answers the facts a call takes from its arguments before its own rule: the first invalid argument, or the inputs dependent arguments miss; null when every argument is decided.
     *
     * @param list<ScalarFact> $arguments
     */
    public function inputs(array $arguments): ?ScalarFact
    {
        $missing = [];
        foreach ($arguments as $argument) {
            if ($argument->type instanceof Invalid) {
                return new ScalarFact($argument->type, Nullability::Dependent);
            }
            if ($argument->type instanceof Dependent) {
                $missing = [...$missing, ...$argument->type->missing];
            }
        }

        return $missing === [] ? null : new ScalarFact(new Dependent($missing), Nullability::Dependent);
    }

    /**
     * Tells whether `pg_catalog` is the first schema searched for functions.
     */
    public function catalogFirst(AnalysisContext $context): bool
    {
        foreach ($context->searchPath as $schema) {
            if ($schema->value !== 'pg_temp') {
                return $schema->value === 'pg_catalog';
            }
        }

        return false;
    }
}
