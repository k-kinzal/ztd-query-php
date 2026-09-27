<?php

declare(strict_types=1);

namespace Tests\Unit\Report;

use JsonException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Report\QueryEncoding
 */
#[CoversClass(\Deriver\Report\QueryEncoding::class)]
#[UsesClass(\Deriver\Api\Project\EntryPoint::class)]
#[UsesClass(\Deriver\Api\Query\Budget::class)]
#[UsesClass(\Deriver\Api\Query\QueryScope::class)]
#[UsesClass(\Deriver\Api\Query\ReturnQuery::class)]
#[UsesClass(\Deriver\Api\Query\StateQuery::class)]
#[UsesClass(\Deriver\Api\Query\TupleQuery::class)]
#[UsesClass(\Deriver\Api\Query\ValueQuery::class)]
#[UsesClass(\Deriver\Api\Reference\ExpressionRef::class)]
#[UsesClass(\Deriver\Api\Reference\PointRef::class)]
#[UsesClass(\Deriver\Api\Reference\SourceRef::class)]
#[UsesClass(\Deriver\Internal\Value\Identity::class)]
#[UsesClass(\Deriver\Internal\Value\SecretFingerprint::class)]
#[UsesClass(\Deriver\Report\JsonText::class)]
#[UsesClass(\Deriver\Report\ValueGraph::class)]
#[UsesClass(\Deriver\Value\Projection::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class QueryEncodingTest extends TestCase
{
    public function testRecordUsesTheSharedValueGraphForEntryArguments(): void
    {
        $query = new \Deriver\Api\Query\ReturnQuery('target', \Deriver\Api\Query\QueryScope::fromEntrypoints([new \Deriver\Api\Project\EntryPoint('main', [\Deriver\Value\Term::constant(7)])]));
        $graph = new \Deriver\Report\ValueGraph();
        $record = (new \Deriver\Report\QueryEncoding())->record($query, $graph);
        self::assertSame('entrypoint', $record['scope']['mode']);
        self::assertArrayHasKey($record['scope']['entries'][0]['arguments'][0]['value'], $graph->records);
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testKeyIncludesStructuralBudgetsAndEntryValues(): void
    {
        $query = new \Deriver\Api\Query\ReturnQuery('target');
        $limited = new \Deriver\Api\Query\ReturnQuery('target', budget:new \Deriver\Api\Query\Budget(transfers:1));
        $encoder = new \Deriver\Report\QueryEncoding();
        self::assertSame($encoder->key($query), $encoder->key($query));
        self::assertNotSame($encoder->key($query), $encoder->key($limited));
    }
    public function testRecordPreservesEveryObservationKindAndItsExactReferences(): void
    {
        $at = new \Deriver\Api\Reference\SourceRef('snapshot', 'a.php', 4, 9, 2);
        $expression = new \Deriver\Api\Reference\ExpressionRef($at, 'target', 'r2');
        $point = new \Deriver\Api\Reference\PointRef($at, 'target', 'i3', 'after');
        $projection = \Deriver\Value\Projection::stateSlot('cache', ['entry',2]);
        $budget = new \Deriver\Api\Query\Budget(13, 3, 4, 5, 6);
        $encoder = new \Deriver\Report\QueryEncoding();
        $graph = new \Deriver\Report\ValueGraph();
        $returned = $encoder->record(new \Deriver\Api\Query\ReturnQuery('target', budget:$budget), $graph);
        $value = $encoder->record(new \Deriver\Api\Query\ValueQuery($expression, $projection, budget:$budget), $graph);
        $state = $encoder->record(new \Deriver\Api\Query\StateQuery($point, 'local', $projection, budget:$budget), $graph);
        $tuple = $encoder->record(new \Deriver\Api\Query\TupleQuery($point, ['chosen' => $expression], budget:$budget), $graph);
        self::assertSame(['kind' => 'return','symbol' => 'target','scope' => ['mode' => 'symbolic','entries' => []],'budget' => $budget], $returned);
        self::assertSame(['kind' => 'value','expression' => $expression,'projection' => $projection,'scope' => ['mode' => 'symbolic','entries' => []],'budget' => $budget], $value);
        self::assertSame(['kind' => 'state','point' => $point,'variable' => 'local','projection' => $projection,'scope' => ['mode' => 'symbolic','entries' => []],'budget' => $budget], $state);
        self::assertSame('tuple', $tuple['kind']);
        self::assertArrayHasKey('point', $tuple);
        self::assertSame($point, $tuple['point'] ?? null);
        self::assertArrayHasKey('values', $tuple);
        self::assertEquals((object)['chosen' => $expression], $tuple['values'] ?? null);
        self::assertSame($budget, $tuple['budget']);
        self::assertSame(['mode' => 'symbolic','entries' => []], $tuple['scope']);
        self::assertSame([], $graph->records);
    }

    public function testRecordKeepsEntryOrderNamedArgumentsAndReceiverInTheSharedGraph(): void
    {
        $shared = \Deriver\Value\Term::constant(7);
        $receiver = new \Deriver\Value\Term('object', 'box', attributes:['class' => 'Box']);
        $scope = \Deriver\Api\Query\QueryScope::fromEntrypoints([
            new \Deriver\Api\Project\EntryPoint('Box::run', [0 => $shared,'value' => $shared], $receiver),
            new \Deriver\Api\Project\EntryPoint('main', [\Deriver\Value\Term::constant(null)]),
        ]);
        $graph = new \Deriver\Report\ValueGraph();
        $record = (new \Deriver\Report\QueryEncoding())->record(new \Deriver\Api\Query\ReturnQuery('target', $scope), $graph);
        $entries = $record['scope']['entries'];
        self::assertSame('entrypoint', $record['scope']['mode']);
        self::assertCount(2, $entries);
        self::assertSame('Box::run', $entries[0]['symbol']);
        self::assertSame('main', $entries[1]['symbol']);
        self::assertSame([
            ['name' => $graph->scalar(0),'value' => $graph->add($shared)],
            ['name' => $graph->scalar('value'),'value' => $graph->add($shared)],
        ], $entries[0]['arguments']);
        self::assertSame($graph->add($receiver), $entries[0]['receiver']);
        self::assertNull($entries[1]['receiver']);
        self::assertSame([['name' => $graph->scalar(0),'value' => $graph->add(\Deriver\Value\Term::constant(null))]], $entries[1]['arguments']);
        self::assertCount(3, $graph->records);
    }

    /**
     * @throws JsonException If query metadata cannot be encoded
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerDistinctQueries')]
    public function testKeyDistinguishesEverySemanticQueryOption(\Deriver\Api\Query\Query $left, \Deriver\Api\Query\Query $right): void
    {
        $encoder = new \Deriver\Report\QueryEncoding();
        self::assertNotSame($encoder->key($left), $encoder->key($right));
        self::assertSame($encoder->key($left), $encoder->key(clone $left));
    }

    /**
     * @return iterable<string,array{\Deriver\Api\Query\Query,\Deriver\Api\Query\Query}>
     */
    public static function providerDistinctQueries(): iterable
    {
        $at = new \Deriver\Api\Reference\SourceRef('snapshot', 'a.php', 4, 9, 2);
        $expression = new \Deriver\Api\Reference\ExpressionRef($at, 'target', 'r2');
        $point = new \Deriver\Api\Reference\PointRef($at, 'target', 'i3', 'after');
        $base = new \Deriver\Api\Query\ReturnQuery('target');
        yield 'symbol' => [$base,new \Deriver\Api\Query\ReturnQuery('other')];
        yield 'kind' => [$base,new \Deriver\Api\Query\ValueQuery($expression)];
        yield 'transfers' => [$base,new \Deriver\Api\Query\ReturnQuery('target', budget:new \Deriver\Api\Query\Budget(transfers:3))];
        yield 'partitions' => [$base,new \Deriver\Api\Query\ReturnQuery('target', budget:new \Deriver\Api\Query\Budget(partitions:3))];
        yield 'iterations' => [$base,new \Deriver\Api\Query\ReturnQuery('target', budget:new \Deriver\Api\Query\Budget(iterations:3))];
        yield 'recursion' => [$base,new \Deriver\Api\Query\ReturnQuery('target', budget:new \Deriver\Api\Query\Budget(recursion:3))];
        yield 'nodes' => [$base,new \Deriver\Api\Query\ReturnQuery('target', budget:new \Deriver\Api\Query\Budget(nodes:3))];
        yield 'scope' => [$base,new \Deriver\Api\Query\ReturnQuery('target', \Deriver\Api\Query\QueryScope::fromEntrypoints([new \Deriver\Api\Project\EntryPoint('target')]))];
        yield 'expression' => [new \Deriver\Api\Query\ValueQuery($expression),new \Deriver\Api\Query\ValueQuery(new \Deriver\Api\Reference\ExpressionRef($at, 'target', 'r3'))];
        yield 'path' => [new \Deriver\Api\Query\ValueQuery($expression),new \Deriver\Api\Query\ValueQuery($expression, new \Deriver\Value\Projection(['key']))];
        yield 'slot' => [new \Deriver\Api\Query\ValueQuery($expression),new \Deriver\Api\Query\ValueQuery($expression, \Deriver\Value\Projection::stateSlot('cache'))];
        yield 'variable' => [new \Deriver\Api\Query\StateQuery($point, 'a'),new \Deriver\Api\Query\StateQuery($point, 'b')];
        yield 'phase' => [new \Deriver\Api\Query\StateQuery($point, 'a'),new \Deriver\Api\Query\StateQuery(new \Deriver\Api\Reference\PointRef($at, 'target', 'i3', 'before'), 'a')];
        yield 'tuple labels' => [new \Deriver\Api\Query\TupleQuery($point, ['a' => $expression]),new \Deriver\Api\Query\TupleQuery($point, ['b' => $expression])];
        yield 'tuple membership' => [new \Deriver\Api\Query\TupleQuery($point, []),new \Deriver\Api\Query\TupleQuery($point, ['a' => $expression])];
        yield 'snapshot' => [new \Deriver\Api\Query\ValueQuery($expression),new \Deriver\Api\Query\ValueQuery(new \Deriver\Api\Reference\ExpressionRef(new \Deriver\Api\Reference\SourceRef('other', 'a.php', 4, 9, 2), 'target', 'r2'))];
    }

    /**
     * @throws JsonException If query metadata cannot be encoded
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerEntryIdentity')]
    public function testKeyRetainsEntryContentAndConfidentiality(\Deriver\Api\Project\EntryPoint $left, \Deriver\Api\Project\EntryPoint $right): void
    {
        $encoder = new \Deriver\Report\QueryEncoding();
        $a = new \Deriver\Api\Query\ReturnQuery('target', \Deriver\Api\Query\QueryScope::fromEntrypoints([$left]));
        $b = new \Deriver\Api\Query\ReturnQuery('target', \Deriver\Api\Query\QueryScope::fromEntrypoints([$right]));
        self::assertNotSame($encoder->key($a), $encoder->key($b));
        self::assertSame($encoder->key($a), $encoder->key($a));
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $encoder->key($a));
    }

    /**
     * @return iterable<string,array{\Deriver\Api\Project\EntryPoint,\Deriver\Api\Project\EntryPoint}>
     */
    public static function providerEntryIdentity(): iterable
    {
        yield 'symbol' => [new \Deriver\Api\Project\EntryPoint('a'),new \Deriver\Api\Project\EntryPoint('b')];
        yield 'argument value' => [new \Deriver\Api\Project\EntryPoint('a', [\Deriver\Value\Term::constant(1)]),new \Deriver\Api\Project\EntryPoint('a', [\Deriver\Value\Term::constant(2)])];
        yield 'argument name' => [new \Deriver\Api\Project\EntryPoint('a', ['x' => \Deriver\Value\Term::constant(1)]),new \Deriver\Api\Project\EntryPoint('a', ['y' => \Deriver\Value\Term::constant(1)])];
        yield 'argument secrecy' => [new \Deriver\Api\Project\EntryPoint('a', [\Deriver\Value\Term::constant('secret', true)]),new \Deriver\Api\Project\EntryPoint('a', [\Deriver\Value\Term::constant('secret')])];
        yield 'confidential values' => [new \Deriver\Api\Project\EntryPoint('a', [\Deriver\Value\Term::constant('one', true)]),new \Deriver\Api\Project\EntryPoint('a', [\Deriver\Value\Term::constant('two', true)])];
        yield 'receiver' => [new \Deriver\Api\Project\EntryPoint('Box::run', receiver:new \Deriver\Value\Term('object', 'a', attributes:['class' => 'Box'])),new \Deriver\Api\Project\EntryPoint('Box::run')];
        yield 'receiver secrecy' => [new \Deriver\Api\Project\EntryPoint('Box::run', receiver:new \Deriver\Value\Term('object', 'a', attributes:['class' => 'Box'], secret:true)),new \Deriver\Api\Project\EntryPoint('Box::run', receiver:new \Deriver\Value\Term('object', 'a', attributes:['class' => 'Box']))];
        yield 'binary argument name' => [new \Deriver\Api\Project\EntryPoint('a', ["\xff" => \Deriver\Value\Term::constant(1)]),new \Deriver\Api\Project\EntryPoint('a', ["\xfe" => \Deriver\Value\Term::constant(1)])];
    }

    public function testRecordRejectsUnknownQueryImplementations(): void
    {
        $query = self::createStub(\Deriver\Api\Query\Query::class);
        $this->expectException(\Deriver\Api\InvalidInputException::class);
        $this->expectExceptionMessage('Unsupported query implementation.');
        (new \Deriver\Report\QueryEncoding())->record($query, new \Deriver\Report\ValueGraph());
    }
}
