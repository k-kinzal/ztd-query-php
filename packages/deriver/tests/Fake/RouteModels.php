<?php

declare(strict_types=1);

namespace Tests\Fake;

use Deriver\Model\ModelDescriptor;
use Deriver\Model\Plan\Action;
use Deriver\Model\Plan\Expression;
use Deriver\Model\Plan\SemanticPlan;
use Deriver\Model\Signature\Parameter;
use Deriver\Model\Signature\Signature;
use Deriver\Query\QueryScope;
use Deriver\Query\ValueQuery;
use Deriver\Result\Alternative;
use Deriver\Value\Term;
use JsonException;
use RuntimeException;

/**
 * Models a framework route registration without captured framework source.
 * @visibility root
 */
final class RouteModels
{
    /**
     * @return PlanModel Route registration that runs its handler once and returns the application
     */
    public static function get(): PlanModel
    {
        return new PlanModel(new ModelDescriptor('example.slim-get', '1', 'Slim\App::get', new Signature([new Parameter('pattern', 'string'), new Parameter('callback', 'callable')])), new SemanticPlan([Action::callback('response', Expression::parameter('callback')), Action::returns(Expression::receiver())]));
    }

    /**
     * @return PlanModel Constructor that only allocates the application object
     */
    public static function construct(): PlanModel
    {
        return new PlanModel(new ModelDescriptor('example.slim-app', '1', 'Slim\App::__construct'), new SemanticPlan([Action::returns(Expression::literal(Term::constant(null)))]));
    }

    /**
     * Derives the SQL argument and the receiver of the first query() call.
     * @param \Deriver\Analysis\ExecutionSession $session Analysis session
     * @param QueryScope $scope Entrypoint scope
     * @return array{list<Alternative>, list<Alternative>} SQL and receiver observations
     * @throws RuntimeException If the fixture has no method call to query()
     * @throws JsonException If captured metadata cannot be encoded
     */
    public static function observe(\Deriver\Analysis\ExecutionSession $session, QueryScope $scope): array
    {
        $call = $session->callsTo('query')[0] ?? null;
        if ($call?->receiver === null) {
            throw new RuntimeException('The fixture must call query() on a receiver.');
        }
        return [$session->derive(new ValueQuery($call->argument(0), scope: $scope))->normalOutcomes, $session->derive(new ValueQuery($call->receiver, scope: $scope))->normalOutcomes];
    }
}
