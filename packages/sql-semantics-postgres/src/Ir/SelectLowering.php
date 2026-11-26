<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir;

use SqlSemantics\Platform\PostgreSql\Ir\Expression\CastDestination;
use SqlSemantics\Platform\PostgreSql\Ir\From\JoinBinding;
use SqlSemantics\Platform\PostgreSql\Ir\From\ProjectionLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Mutation\Insert\Destination;
use SqlSemantics\Platform\PostgreSql\Ir\Ordering\ResultLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\PredicateLowering;
use SqlSemantics\Platform\PostgreSql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select as ParsedSelect;

/**
 * Resolves the supported SELECT and constructs its complete value.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class SelectLowering
{
    /**
     * Converts the checked operation into a complete value.
     * @param array<int, CastDestination> $inputTypes Native input types inferred in statement order
     * @throws InvalidConstruction When the operation cannot be represented in this domain
     */
    public function lower(BoundStatement $operation, ?ParsedSelect $operand = null, array &$inputTypes = [], ?Destination $assignment = null, bool $resolvedTextOutput = false): Select
    {
        $profile = $operation->context->profile;
        Check::input($profile->parameters === ParameterStyle::Native, 'The projection requires the native language profile.');
        $statement = $operand ?? $operation->statement;
        Check::input($statement instanceof ParsedSelect, 'Complete PostgreSQL IR currently supports a single projection SELECT.');
        Check::input(
            $statement->groupBy === [] && $statement->having === null
                && $statement->windows === [] && ($statement->distinct->on ?? []) === [] && $statement->into === null
                && ($statement->options->locking ?? []) === [] && !($statement->options->readOnly ?? false),
            'Complete PostgreSQL IR currently supports projections with positional result selection.',
        );
        Check::input($statement->targets !== [], 'A complete SELECT needs a nonempty projection.');
        $searchPath = $operation->context->searchPath;
        $operators = $searchPath[0]->value === 'pg_temp' ? ($searchPath[1]->value ?? null) : $searchPath[0]->value;
        $catalogFirst = $operators === 'pg_catalog';
        $binding = JoinBinding::bind($operation, $statement->from, $catalogFirst);
        $where = (new PredicateLowering())->lower($statement->where, $binding, $catalogFirst);
        $results = (new ProjectionLowering())->lower($operation, $statement, $binding, $catalogFirst, $inputTypes, $assignment, $operand === null || $resolvedTextOutput);

        $result = new ResultLowering();
        $order = $result->orders($operation, $statement, $binding, $results, $catalogFirst);

        return new Select($results, $binding->source(), $profile->grammar, $where, $statement->distinct !== null, $order, $result->limit($statement->options?->limit));
    }
}
