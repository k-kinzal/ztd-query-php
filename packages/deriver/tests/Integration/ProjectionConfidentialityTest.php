<?php

declare(strict_types=1);

namespace Tests\Integration;

use Deriver\Api\Project\EntryPoint;
use Deriver\Api\Query\QueryScope;
use Deriver\Api\Query\StateQuery;
use Deriver\Api\Query\ValueQuery;
use Deriver\Value\Projection;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Tests\Fake\Analysis;

#[CoversNothing]
#[Small]
final class ProjectionConfidentialityTest extends TestCase
{
    /**
     * @throws JsonException If the result cannot be encoded
     */
    public function testValueProjectionPreservesExplicitAggregateConfidentiality(): void
    {
        $session = Analysis::session('<?php function sink($value){}function target($input){sink($input);}');
        $input = new Term('array', operands: ['keep' => Term::constant('projected-private-value')], attributes: ['open' => false], secret: true);
        $scope = QueryScope::fromEntrypoints([new EntryPoint('target', [$input])]);
        $query = new ValueQuery($session->callsTo('sink')[0]->argument(0), new Projection(['keep']), $scope);
        $result = $session->derive($query);
        self::assertSame([], $result->frontiers);
        self::assertCount(1, $result->normalOutcomes);
        self::assertTrue($result->normalOutcomes[0]->values['value']->isSecret());
        self::assertStringNotContainsString(base64_encode('projected-private-value'), $result->toJson());
        self::assertStringContainsString(base64_encode('projected-private-value'), $result->toJson(true));
    }

    /**
     * @throws JsonException If the result cannot be encoded
     */
    public function testStateProjectionPreservesExplicitAggregateConfidentiality(): void
    {
        $session = Analysis::session('<?php function sink($value){}function target($input){sink($input);}');
        $input = new Term('array', operands: ['keep' => Term::constant('projected-private-value')], attributes: ['open' => false], secret: true);
        $scope = QueryScope::fromEntrypoints([new EntryPoint('target', [$input])]);
        $query = new StateQuery($session->callsTo('sink')[0]->beforeInvocation(), 'input', new Projection(['keep']), $scope);
        $result = $session->derive($query);
        self::assertSame([], $result->frontiers);
        self::assertCount(1, $result->normalOutcomes);
        self::assertTrue($result->normalOutcomes[0]->values['state']->isSecret());
        self::assertStringNotContainsString(base64_encode('projected-private-value'), $result->toJson());
        self::assertStringContainsString(base64_encode('projected-private-value'), $result->toJson(true));
    }
}
