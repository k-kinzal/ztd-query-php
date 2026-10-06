<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Invocation;

/**
 * Selects the `pg_catalog` function a call certainly resolves to, from the argument types.
 *
 * Rule: PG-ROUTINE-MATCH-001. With every schema searched (an unqualified
 * name), a function of the user with exactly the argument types beats any
 * function that needs a conversion, wherever it lies on the path, so only an
 * exact match is certain: each argument has exactly the parameter type, and a
 * string constant or NULL meets a `text` parameter, the preferred type of the
 * string category every candidate is narrowed to. With `pg_catalog` alone
 * searched (a name qualified with `pg_catalog`, and the SQL-syntax functions
 * the server calls as `pg_catalog.name`), the candidates are those whose
 * parameters the arguments reach by implicit casts; when several remain, the
 * ones taking `text` where an argument is a string constant or NULL are
 * preferred; a single remaining candidate decides the call. Several
 * remaining candidates are left undecided: the server applies further
 * preferences or reports an ambiguity, and this table does not model them.
 * Anything else is left to the routine's declaration. Source: https://www.postgresql.org/docs/17/typeconv-func.html.
 * Termination: one pass over the rows. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class RoutineMatch
{
    /**
     * Answers the row a call of a catalog function resolves to, or null when it is not certain.
     *
     * @param list<string> $arguments The catalog type names of the arguments
     * @param bool $catalogOnly Whether `pg_catalog` alone is searched
     *
     * @return array{string, string, string, list<string>}|null
     */
    public function find(string $name, array $arguments, bool $catalogOnly): ?array
    {
        $coercions = new Coercions();
        $candidates = [];
        foreach ((new Signatures())->rows($name) as $row) {
            if (count($row[3]) !== count($arguments)) {
                continue;
            }
            $exact = true;
            $reachable = true;
            foreach ($row[3] as $position => $parameter) {
                $argument = $arguments[$position];
                $exact = $exact && ($argument === $parameter || ($argument === 'unknown' && $parameter === 'text'));
                $reachable = $reachable && $coercions->implicit($argument, $parameter);
            }
            if ($exact) {
                return $row;
            }
            if ($catalogOnly && $reachable) {
                $candidates[] = $row;
            }
        }

        return $this->single($this->preferred($candidates, $arguments));
    }

    /**
     * Keeps the candidates that take `text` at every position of an argument of type `unknown`, when there are such candidates.
     *
     * @param list<array{string, string, string, list<string>}> $candidates
     * @param list<string> $arguments
     *
     * @return list<array{string, string, string, list<string>}>
     */
    public function preferred(array $candidates, array $arguments): array
    {
        $kept = [];
        foreach ($candidates as $row) {
            $textual = true;
            foreach ($arguments as $position => $argument) {
                $textual = $textual && ($argument !== 'unknown' || $row[3][$position] === 'text');
            }
            if ($textual) {
                $kept[] = $row;
            }
        }

        return $kept === [] ? $candidates : $kept;
    }

    /**
     * Answers the only candidate, or null when there is none or several.
     *
     * @param list<array{string, string, string, list<string>}> $candidates
     *
     * @return array{string, string, string, list<string>}|null
     */
    public function single(array $candidates): ?array
    {
        return count($candidates) === 1 ? $candidates[0] : null;
    }
}
