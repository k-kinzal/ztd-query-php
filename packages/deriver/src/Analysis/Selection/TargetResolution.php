<?php

declare(strict_types=1);

namespace Deriver\Analysis\Selection;

use Deriver\Candidate\Target;
use Deriver\ControlFlow\Program;
use Deriver\Exception\InvalidInputException;
use Deriver\Query\ParameterQuery;
use Deriver\Query\Query;
use Deriver\Query\ReturnQuery;
use Deriver\Query\StateQuery;
use Deriver\Query\TupleQuery;
use Deriver\Query\ValueQuery;
use Deriver\Reference\ExpressionRef;

/**
 * Binds a query to one point of the captured program after validating every reference it carries.
 * This is the selection stage; it reads declarations and lowered bodies but expands no definition.
 * @visibility parent
 */
final class TargetResolution
{
    /**
     * @param Program $program Captured program
     * @param string $snapshotId Snapshot the query's references must belong to
     */
    public function __construct(public readonly Program $program, public readonly string $snapshotId)
    {
    }

    /**
     * Resolves the owner and the selected definition of a query.
     * @throws InvalidInputException If any reference is foreign, fabricated or incompatible
     */
    public function resolve(Query $query): Target
    {
        $owner = (new QueryValidation($this->program, $this->snapshotId))->owner($query);
        if ($query instanceof ParameterQuery) {
            return new Target($query, $owner, 'parameter', $query->parameter, parameter: $query->parameter);
        }
        if ($query instanceof ReturnQuery) {
            return new Target($query, $owner, 'return', $owner);
        }
        if ($query instanceof ValueQuery) {
            return new Target($query, $owner, 'value', $query->expression->register, $query->expression->source, $query->expression->register, projection: $query->projection);
        }
        if ($query instanceof TupleQuery) {
            return new Target($query, $owner, 'tuple', $owner, $query->point->source, registers: array_map(static fn (ExpressionRef $reference): string => $reference->register, $query->values));
        }
        if ($query instanceof StateQuery) {
            return new Target($query, $owner, 'state', $query->variable, $query->point->source, point: $query->point, variable: $query->variable, projection: $query->projection);
        }
        throw new InvalidInputException('Unsupported query implementation.');
    }
}
