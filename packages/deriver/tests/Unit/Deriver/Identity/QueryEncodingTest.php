<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Identity;

use Deriver\Deriver\Evaluator\Projection;
use Deriver\Deriver\Evaluator\Request\Budget;
use Deriver\Deriver\Evaluator\Request\EntryPoint;
use Deriver\Deriver\Evaluator\Request\Query;
use Deriver\Deriver\Evaluator\Request\QueryScope;
use Deriver\Deriver\Evaluator\Request\ReturnQuery;
use Deriver\Deriver\Evaluator\Request\StateQuery;
use Deriver\Deriver\Evaluator\Request\TupleQuery;
use Deriver\Deriver\Evaluator\Request\ValueQuery;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Identity\QueryEncoding;
use Deriver\Deriver\Identity\ValueGraph;
use Deriver\Deriver\Lowerer\Graph\ExpressionRef;
use Deriver\Deriver\Lowerer\Graph\PointRef;
use Deriver\Deriver\Sources\SourceRef;
use Deriver\Exception\InvalidInputException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Deriver\Identity\QueryEncoding
 */
#[CoversClass(QueryEncoding::class)]
#[UsesClass(EntryPoint::class)]
#[UsesClass(Budget::class)]
#[UsesClass(QueryScope::class)]
#[UsesClass(ReturnQuery::class)]
#[UsesClass(StateQuery::class)]
#[UsesClass(TupleQuery::class)]
#[UsesClass(ValueQuery::class)]
#[UsesClass(ExpressionRef::class)]
#[UsesClass(PointRef::class)]
#[UsesClass(SourceRef::class)]
#[UsesClass(ValueGraph::class)]
#[UsesClass(\Deriver\Deriver\Evaluator\Scalar\Identity::class)]
#[UsesClass(Projection::class)]
#[UsesClass(Term::class)]
#[Small]
final class QueryEncodingTest extends TestCase
{
    public function testKeyDoesNotDependOnSharedSourceOrExpressionInstances(): void
    {
        $source = new SourceRef('snapshot', "source-\xff.php", 4, 9, 2);
        $point = new PointRef($source, 'target', 'i3', \Deriver\Deriver\Lowerer\Graph\ObservationPhase::After);
        $expression = new ExpressionRef($source, 'target', 'r2');
        $shared = new TupleQuery($point, ['first' => $expression, 'second' => $expression]);
        $separate = new TupleQuery(
            new PointRef(clone $source, 'target', 'i3', \Deriver\Deriver\Lowerer\Graph\ObservationPhase::After),
            ['first' => new ExpressionRef(clone $source, 'target', 'r2'), 'second' => new ExpressionRef(clone $source, 'target', 'r2')],
        );
        $encoder = new QueryEncoding();
        self::assertSame($encoder->key($shared), $encoder->key($separate));
    }

    public function testKeyPreservesArbitraryIdentifierBytes(): void
    {
        $encoder = new QueryEncoding();
        self::assertNotSame($encoder->key(new ReturnQuery("\xff")), $encoder->key(new ReturnQuery("\xfe")));
        self::assertNotSame($encoder->key(new ReturnQuery("\xff")), $encoder->key(new ReturnQuery('~b64~/w==')));
    }

    public function testRecordUsesTheSharedValueGraphForEntryArguments(): void
    {
        $query = new ReturnQuery('target', QueryScope::fromEntrypoints([new EntryPoint('main', [Term::constant(7)])]));
        $graph = new ValueGraph();
        $record = (new QueryEncoding())->record($query, $graph);
        self::assertSame('entrypoint', $record['scope']['mode']);
        self::assertArrayHasKey($record['scope']['entries'][0]['arguments'][0]['value'], $graph->records);
    }
    public function testKeyIncludesStructuralBudgetsAndEntryValues(): void
    {
        $query = new ReturnQuery('target');
        $limited = new ReturnQuery('target', budget:new Budget(transfers:1));
        $encoder = new QueryEncoding();
        self::assertSame($encoder->key($query), $encoder->key($query));
        self::assertNotSame($encoder->key($query), $encoder->key($limited));
    }
    public function testRecordPreservesEveryObservationKindAndItsExactReferences(): void
    {
        $at = new SourceRef('snapshot', 'a.php', 4, 9, 2);
        $expression = new ExpressionRef($at, 'target', 'r2');
        $point = new PointRef($at, 'target', 'i3', \Deriver\Deriver\Lowerer\Graph\ObservationPhase::After);
        $projection = Projection::stateSlot('cache', ['entry',2]);
        $budget = new Budget(13, 3, 4, 5, 6);
        $encoder = new QueryEncoding();
        $graph = new ValueGraph();
        $returned = $encoder->record(new ReturnQuery('target', budget:$budget), $graph);
        $value = $encoder->record(new ValueQuery($expression, $projection, budget:$budget), $graph);
        $state = $encoder->record(new StateQuery($point, 'local', $projection, budget:$budget), $graph);
        $tuple = $encoder->record(new TupleQuery($point, ['chosen' => $expression], budget:$budget), $graph);
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
        $shared = Term::constant(7);
        $receiver = new Term(\Deriver\Deriver\Evaluator\TermKind::Object, 'box', attributes:['class' => 'Box']);
        $scope = QueryScope::fromEntrypoints([
            new EntryPoint('Box::run', [0 => $shared,'value' => $shared], $receiver),
            new EntryPoint('main', [Term::constant(null)]),
        ]);
        $graph = new ValueGraph();
        $record = (new QueryEncoding())->record(new ReturnQuery('target', $scope), $graph);
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
        self::assertSame([['name' => $graph->scalar(0),'value' => $graph->add(Term::constant(null))]], $entries[1]['arguments']);
        self::assertCount(3, $graph->records);
    }

    public function testRecordKeepsReceiverPropertiesByNameInTheSharedGraph(): void
    {
        $order = Term::constant('name');
        $graph = new ValueGraph();
        $record = (new QueryEncoding())->record(new ReturnQuery('target', QueryScope::fromEntrypoints([new EntryPoint('Box::run', [$order], properties:['order' => $order,'table' => Term::constant('users')]), new EntryPoint('main')])), $graph);
        $entries = $record['scope']['entries'];
        self::assertSame([['name' => 'order','value' => $graph->add($order)],['name' => 'table','value' => $graph->add(Term::constant('users'))]], $entries[0]['properties']);
        self::assertSame($entries[0]['arguments'][0]['value'], $entries[0]['properties'][0]['value']);
        self::assertSame([], $entries[1]['properties']);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerDistinctQueries')]
    public function testKeyDistinguishesEverySemanticQueryOption(Query $left, Query $right): void
    {
        $encoder = new QueryEncoding();
        self::assertNotSame($encoder->key($left), $encoder->key($right));
        self::assertSame($encoder->key($left), $encoder->key(clone $left));
    }

    /**
     * @return iterable<string,array{Query,Query}>
     */
    public static function providerDistinctQueries(): iterable
    {
        $at = new SourceRef('snapshot', 'a.php', 4, 9, 2);
        $expression = new ExpressionRef($at, 'target', 'r2');
        $point = new PointRef($at, 'target', 'i3', \Deriver\Deriver\Lowerer\Graph\ObservationPhase::After);
        $base = new ReturnQuery('target');
        yield 'symbol' => [$base,new ReturnQuery('other')];
        yield 'kind' => [$base,new ValueQuery($expression)];
        yield 'transfers' => [$base,new ReturnQuery('target', budget:new Budget(transfers:3))];
        yield 'partitions' => [$base,new ReturnQuery('target', budget:new Budget(partitions:3))];
        yield 'iterations' => [$base,new ReturnQuery('target', budget:new Budget(iterations:3))];
        yield 'recursion' => [$base,new ReturnQuery('target', budget:new Budget(recursion:3))];
        yield 'nodes' => [$base,new ReturnQuery('target', budget:new Budget(nodes:3))];
        yield 'scope' => [$base,new ReturnQuery('target', QueryScope::fromEntrypoints([new EntryPoint('target')]))];
        yield 'expression' => [new ValueQuery($expression),new ValueQuery(new ExpressionRef($at, 'target', 'r3'))];
        yield 'path' => [new ValueQuery($expression),new ValueQuery($expression, new Projection(['key']))];
        yield 'slot' => [new ValueQuery($expression),new ValueQuery($expression, Projection::stateSlot('cache'))];
        yield 'variable' => [new StateQuery($point, 'a'),new StateQuery($point, 'b')];
        yield 'phase' => [new StateQuery($point, 'a'),new StateQuery(new PointRef($at, 'target', 'i3', \Deriver\Deriver\Lowerer\Graph\ObservationPhase::Before), 'a')];
        yield 'tuple labels' => [new TupleQuery($point, ['a' => $expression]),new TupleQuery($point, ['b' => $expression])];
        yield 'tuple membership' => [new TupleQuery($point, []),new TupleQuery($point, ['a' => $expression])];
        yield 'snapshot' => [new ValueQuery($expression),new ValueQuery(new ExpressionRef(new SourceRef('other', 'a.php', 4, 9, 2), 'target', 'r2'))];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerEntryIdentity')]
    public function testKeyDistinguishesEntryContent(EntryPoint $left, EntryPoint $right): void
    {
        $encoder = new QueryEncoding();
        $a = new ReturnQuery('target', QueryScope::fromEntrypoints([$left]));
        $b = new ReturnQuery('target', QueryScope::fromEntrypoints([$right]));
        self::assertNotSame($encoder->key($a), $encoder->key($b));
        self::assertSame($encoder->key($a), $encoder->key($a));
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $encoder->key($a));
    }

    /**
     * @return iterable<string,array{EntryPoint,EntryPoint}>
     */
    public static function providerEntryIdentity(): iterable
    {
        yield 'symbol' => [new EntryPoint('a'),new EntryPoint('b')];
        yield 'argument value' => [new EntryPoint('a', [Term::constant(1)]),new EntryPoint('a', [Term::constant(2)])];
        yield 'argument name' => [new EntryPoint('a', ['x' => Term::constant(1)]),new EntryPoint('a', ['y' => Term::constant(1)])];
        yield 'argument values' => [new EntryPoint('a', [Term::constant('one')]),new EntryPoint('a', [Term::constant('two')])];
        yield 'receiver' => [new EntryPoint('Box::run', receiver:new Term(\Deriver\Deriver\Evaluator\TermKind::Object, 'a', attributes:['class' => 'Box'])),new EntryPoint('Box::run')];
        yield 'binary argument name' => [new EntryPoint('a', ["\xff" => Term::constant(1)]),new EntryPoint('a', ["\xfe" => Term::constant(1)])];
        yield 'property value' => [new EntryPoint('Box::run', properties:['order' => Term::constant('name')]),new EntryPoint('Box::run', properties:['order' => Term::constant('email')])];
        yield 'property name' => [new EntryPoint('Box::run', properties:['order' => Term::constant('name')]),new EntryPoint('Box::run', properties:['table' => Term::constant('name')])];
        yield 'property presence' => [new EntryPoint('Box::run', properties:['order' => Term::constant('name')]),new EntryPoint('Box::run')];
        yield 'property values' => [new EntryPoint('Box::run', properties:['order' => Term::constant('one')]),new EntryPoint('Box::run', properties:['order' => Term::constant('two')])];
    }

    public function testRecordRejectsUnknownQueryImplementations(): void
    {
        $query = self::createStub(Query::class);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('Unsupported query implementation.');
        (new QueryEncoding())->record($query, new ValueGraph());
    }    public function testKeyDistinguishesCallPathsAndTheirObservationAnchors(): void
    {
        $path = new \Deriver\Deriver\Evaluator\Request\CallPath(['entry','...','bar']);
        $direct = new \Deriver\Deriver\Evaluator\Request\CallPath(['entry','bar']);
        $atCall = new \Deriver\Deriver\Evaluator\Request\CallPath($path->callers, true);
        $encoder = new QueryEncoding();
        self::assertNotSame($encoder->key(new ReturnQuery('foo')), $encoder->key(new ReturnQuery('foo', QueryScope::through($path))));
        self::assertNotSame($encoder->key(new ReturnQuery('foo', QueryScope::through($direct))), $encoder->key(new ReturnQuery('foo', QueryScope::through($path))));
        self::assertNotSame($encoder->key(new ReturnQuery('foo', QueryScope::through($atCall))), $encoder->key(new ReturnQuery('foo', QueryScope::through($path))));
    }


}
