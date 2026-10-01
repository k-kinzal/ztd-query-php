<?php

declare(strict_types=1);

namespace Tests\Integration;

use Deriver\Project\EntryPoint;
use Deriver\Query\QueryScope;
use Deriver\Query\ReturnQuery;
use Deriver\Query\StateQuery;
use Deriver\Query\ValueQuery;
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

    /**
     * @throws JsonException If result data cannot be encoded
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerConfidentialOperations')]
    public function testDerivedArrayOperationsKeepConfidentialPayloadsOutOfDefaultReports(string $expression, Term $input): void
    {
        $session = Analysis::session('<?php function target($input){return ' . $expression . ';}');
        $query = new ReturnQuery('target', scope:QueryScope::fromEntrypoints([new EntryPoint('target', [$input])]));
        $result = $session->derive($query);
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertCount(1, $result->normalOutcomes);
        self::assertTrue($result->normalOutcomes[0]->values['return']->isSecret());
        self::assertStringNotContainsString(base64_encode('private-union-value'), $result->toJson());
        self::assertStringContainsString(base64_encode('private-union-value'), $result->toJson(true));
    }

    /**
     * @return iterable<string,array{string,Term}>
     */
    public static function providerConfidentialOperations(): iterable
    {
        $array = new Term('array', operands:['secret' => Term::constant('private-union-value')], attributes:['open' => false], secret:true);
        yield 'left union' => ['$input + []', $array];
        yield 'right union' => ['[] + $input', $array];
        yield 'selected left union' => ['($input + [])["secret"]', $array];
        yield 'selected right union' => ['([] + $input)["secret"]', $array];
        yield 'scalar cast' => ['(array)$input', Term::constant('private-union-value', true)];
    }

    /**
     * @throws JsonException If source metadata cannot be encoded
     */
    public function testEmptyArrayConversionRetainsConfidentialAbsence(): void
    {
        $session = Analysis::session('<?php function target($input){return (array)$input;}');
        $result = $session->derive(new ReturnQuery('target', scope:QueryScope::fromEntrypoints([new EntryPoint('target', [Term::constant(null, true)])])));
        self::assertSame([], $result->frontiers);
        self::assertCount(1, $result->normalOutcomes);
        self::assertSame([], $result->normalOutcomes[0]->values['return']->native());
        self::assertTrue($result->normalOutcomes[0]->values['return']->isSecret());
    }
}
