<?php

declare(strict_types=1);

namespace Deriver\Deriver\Selection;

use Deriver\Deriver\Evaluator\Request\ParameterQuery;
use Deriver\Deriver\Evaluator\Request\Query;
use Deriver\Deriver\Evaluator\Request\ReturnQuery;
use Deriver\Deriver\Evaluator\Request\StateQuery;
use Deriver\Deriver\Evaluator\Request\TupleQuery;
use Deriver\Deriver\Evaluator\Request\ValueQuery;
use Deriver\Deriver\Evaluator\Target;
use Deriver\Deriver\Lowerer\Graph\ExpressionRef;
use Deriver\Deriver\Lowerer\Graph\Program;
use Deriver\Exception\InvalidInputException;

/**
 * Binds a query to one point of the captured program after validating every reference it carries.
 * This is the selection stage; it reads declarations and lowered bodies but expands no definition.
 * @visibility parent
 * @visibility Deriver\Deriver\Publication
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
            return new Target($owner, \Deriver\Deriver\Evaluator\TargetKind::Parameter, $query->parameter, parameter: $query->parameter);
        }
        if ($query instanceof ReturnQuery) {
            return new Target($owner, \Deriver\Deriver\Evaluator\TargetKind::Return, $owner);
        }
        if ($query instanceof ValueQuery) {
            return new Target($owner, \Deriver\Deriver\Evaluator\TargetKind::Value, $query->expression->register, $query->expression->source, $query->expression->register, projection: $query->projection);
        }
        if ($query instanceof TupleQuery) {
            return new Target($owner, \Deriver\Deriver\Evaluator\TargetKind::Tuple, $owner, $query->point->source, registers: array_map(static fn (ExpressionRef $reference): string => $reference->register, $query->values));
        }
        if ($query instanceof StateQuery) {
            return new Target($owner, \Deriver\Deriver\Evaluator\TargetKind::State, $query->variable, $query->point->source, point: $query->point, variable: $query->variable, projection: $query->projection);
        }
        throw new InvalidInputException('Unsupported query implementation.');
    }
}
