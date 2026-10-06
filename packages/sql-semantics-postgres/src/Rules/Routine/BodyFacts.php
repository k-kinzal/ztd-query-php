<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Routine;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\PostgreSql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select;
use SqlSemantics\Platform\PostgreSql\Statement\Query\SetOperation;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Problem\RoutineProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\Problem\RoutineProblemKind;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\ReturnStatement;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Statement;

/**
 * Derives the statements of a `BEGIN ATOMIC` routine body.
 *
 * Rule: PG-ROUTINE-BODY-001. Each statement is analyzed when the routine is
 * created, with the parameters visible as the outermost scope
 * (PG-ROUTINE-PARAMETERS-001): a query or a data-modifying statement is
 * derived as a nested query in that environment, so its own relations shadow
 * the parameters and it returns no rows of the CREATE statement; RETURN
 * derives its expression there. Any other statement, and a SELECT INTO, is a
 * utility command, which the server does not accept in an SQL-standard body;
 * it is derived on its own and reported. Terminates: one pass over the list.
 * Source: https://www.postgresql.org/docs/17/sql-createfunction.html,
 * `interpret_AS_clause` in `src/backend/commands/functioncmds.c` of PostgreSQL 17. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class BodyFacts
{
    /**
     * Derives the statements in the environment of the body.
     *
     * @param list<Statement|ReturnStatement> $statements
     */
    public function derive(array $statements, Derivation $derivation, Environment $environment): void
    {
        foreach ($statements as $statement) {
            if ($statement instanceof ReturnStatement) {
                $statement->deriveClause($derivation, $environment);
                continue;
            }
            if ($statement instanceof Query && $this->first($statement)?->into === null) {
                $derivation->query($statement, $environment);
                continue;
            }
            $derivation->report(new RoutineProblem(RoutineProblemKind::UtilityInBody));
            $derivation->inspected($statement);
        }
    }

    /**
     * Answers the first selection of a query, whose INTO clause makes the query a utility command.
     */
    public function first(Query $query): ?Select
    {
        while ($query instanceof ParenthesizedQuery || $query instanceof QueryExpression || $query instanceof SetOperation) {
            $query = match (true) {
                $query instanceof ParenthesizedQuery => $query->query,
                $query instanceof QueryExpression => $query->body,
                $query instanceof SetOperation => $query->left,
            };
        }

        return $query instanceof Select ? $query : null;
    }
}
