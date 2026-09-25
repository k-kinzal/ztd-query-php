<?php

declare(strict_types=1);

namespace Tests\Unit\Extension\Laravel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Evaluation\ArrayEntry;
use SqlCatalog\Core\Evaluation\ArrayTerm;
use SqlCatalog\Core\Evaluation\Domain;
use SqlCatalog\Core\Evaluation\LiteralTerm;
use SqlCatalog\Core\Evaluation\ObjectTerm;
use SqlCatalog\Core\Evaluation\OpaqueTerm;
use SqlCatalog\Core\Evaluation\PatternTerm;
use SqlCatalog\Core\Text\LiteralText;
use SqlCatalog\Core\Text\TextGeneralization;
use SqlCatalog\Core\Text\TextHole;
use SqlCatalog\Core\Text\TextPattern;
use SqlCatalog\Core\Type\TypeShape;
use SqlCatalog\Extension\Laravel\Clauses;
use SqlCatalog\Extension\Laravel\Grammar;
use SqlCatalog\Extension\Laravel\QueryState;

#[CoversClass(Clauses::class)]
#[UsesClass(Domain::class)]
#[UsesClass(ArrayTerm::class)]
#[UsesClass(ArrayEntry::class)]
#[UsesClass(ObjectTerm::class)]
#[UsesClass(LiteralTerm::class)]
#[UsesClass(OpaqueTerm::class)]
#[UsesClass(PatternTerm::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(TextHole::class)]
#[UsesClass(LiteralText::class)]
#[UsesClass(TextGeneralization::class)]
#[UsesClass(TypeShape::class)]
#[UsesClass(QueryState::class)]
#[UsesClass(Grammar::class)]
final class ClausesTest extends TestCase
{
    public function testApplyDispatchesModifiersAndRejectsUnknownOperations(): void
    {
        $c = new Clauses(new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('sqlite')));
        self::assertTrue($c->apply(new QueryState(), 'distinct', [])?->get('distinct')->soleLiteral()?->value);
        self::assertFalse($c->apply(new QueryState(), 'distinct', [Domain::literal(true)])?->get('problem')->isExact());
        self::assertNull($c->apply(new QueryState(), 'macro', []));
        self::assertSame(2, $c->apply(new QueryState(), 'take', [Domain::literal(2)])?->get('limit')->soleLiteral()?->value);
    }

    public function testSelectResetsBindingsAndAddSelectDeduplicatesColumns(): void
    {
        $c = new Clauses(new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('sqlite')));
        $state = $c->raw(new QueryState(), [Domain::literal('? as x'), QueryState::list([Domain::literal(1)])], 'columns', 'selectBindings');
        $state = $c->select($state, [Domain::literal('id')]);
        $state = $c->select($state, [Domain::literal('id'), Domain::literal('name')], true);
        self::assertSame(['"id"', '"name"'], array_map(static fn (Domain $v): mixed => $v->soleLiteral()?->value, $state->items('columns')));
        self::assertSame([], $state->items('selectBindings'));
        self::assertFalse($c->select(new QueryState(), [QueryState::list([])])->get('problem')->isExact());
    }

    public function testColumnsRequiresCompletePositionalArrays(): void
    {
        $c = new Clauses(new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('sqlite')));
        $id = Domain::literal('id');
        self::assertSame([$id], $c->columns([QueryState::list([$id])]));
        $partial = Domain::of(new ArrayTerm([], false));
        self::assertSame([$partial], $c->columns([$partial]));
        self::assertNull($c->columns([Domain::of(new ArrayTerm([new ArrayEntry(Domain::literal('alias'), $id)]))]));
        self::assertSame([$id, $id], $c->columns([$id, $id]));
    }

    public function testRawKeepsBindingsInTheirComponent(): void
    {
        $c = new Clauses(new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('sqlite')));
        $state = $c->raw(new QueryState(), [Domain::literal('id + ?'), QueryState::list([Domain::literal(2)])], 'orders', 'orderBindings');
        self::assertSame(2, $state->items('orderBindings')[0]->soleLiteral()?->value);
        self::assertSame('id + ?', $state->items('orders')[0]->soleLiteral()?->value);
        self::assertFalse($c->raw(new QueryState(), [], 'orders', 'orderBindings')->get('problem')->isExact());
    }

    public function testOrderValidatesDirectionAndHonorsDescending(): void
    {
        $c = new Clauses(new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('mysql')));
        self::assertSame('`id` desc', $c->order(new QueryState(), [Domain::literal('id')], true)->items('orders')[0]->soleLiteral()?->value);
        self::assertSame('`id` asc', $c->order(new QueryState(), [Domain::literal('id'), Domain::literal('ASC')], false)->items('orders')[0]->soleLiteral()?->value);
        self::assertFalse($c->order(new QueryState(), [Domain::literal('id'), Domain::literal('random')], false)->get('problem')->isExact());
        $open = $c->order(new QueryState(), [Domain::literal('id'), Domain::unknown()], false);
        self::assertArrayNotHasKey('problem', $open->fields);
        self::assertFalse($open->items('orders')[0]->isExact());
        self::assertFalse($c->order(new QueryState(), [Domain::literal('id'), QueryState::list([])], false)->get('problem')->isExact());
        self::assertFalse($c->order(new QueryState(), [], false)->get('problem')->isExact());
    }

    public function testGroupAppendsIdentifiersAndRetainsUnknownColumns(): void
    {
        $c = new Clauses(new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('sqlite')));
        self::assertSame('"team"', $c->group(new QueryState(), [Domain::literal('team')])->items('groups')[0]->soleLiteral()?->value);
        $open = $c->group(new QueryState(), [Domain::of(new ArrayTerm([], false))]);
        self::assertArrayNotHasKey('problem', $open->fields);
        self::assertFalse($open->items('groups')[0]->isExact());
        self::assertFalse($c->group(new QueryState(), [Domain::of(new ArrayTerm([new ArrayEntry(Domain::literal('alias'), Domain::literal('id'))]))])->get('problem')->isExact());
    }

    public function testNumberKeepsUnknownLimitsOpenAndIgnoresNegativeOnes(): void
    {
        $c = new Clauses(new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('sqlite')));
        self::assertSame(0, $c->number(new QueryState(), [Domain::literal(0)], 'limit')->get('limit')->soleLiteral()?->value);
        self::assertSame(10, $c->number(new QueryState(), [Domain::literal('10')], 'limit')->get('limit')->soleLiteral()?->value);
        $kept = $c->number(new QueryState(['limit' => Domain::literal(5)]), [Domain::literal(-1)], 'limit');
        self::assertSame(5, $kept->get('limit')->soleLiteral()?->value);
        self::assertNull($c->number($kept, [Domain::literal(null)], 'limit')->get('limit')->soleLiteral()?->value);
        $open = $c->number(new QueryState(), [Domain::unknown()], 'limit');
        self::assertArrayNotHasKey('problem', $open->fields);
        self::assertFalse($open->get('limit')->isExact());
        self::assertFalse($c->number(new QueryState(), [Domain::literal('ten')], 'limit')->get('problem')->isExact());
        self::assertFalse($c->number(new QueryState(), [QueryState::list([])], 'limit')->get('problem')->isExact());
        self::assertFalse($c->number(new QueryState(), [], 'limit')->get('problem')->isExact());
    }

    public function testJoinQuotesBothColumnOperandsWithoutBindings(): void
    {
        $c = new Clauses(new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('sqlite')));
        $state = $c->join(new QueryState(), array_map(Domain::literal(...), ['posts as p', 'p.user_id', '=', 'u.id']), 'leftjoin');
        self::assertSame('left join "posts" as "p" on "p"."user_id" = "u"."id"', $state->items('joins')[0]->soleLiteral()?->value);
        self::assertFalse($c->join(new QueryState(), [], 'join')->get('problem')->isExact());
        self::assertFalse($c->join(new QueryState(), array_map(Domain::literal(...), ['p', 'a', 'bad', 'b']), 'join')->get('problem')->isExact());
    }

    /**
     * @param list<string|int> $arguments
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerClauseDispatch')]
    public function testApplySelectsTheCorrectClause(string $method, array $arguments, string $field, string|int $expected): void
    {
        $state = (new Clauses(new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('sqlite'))))->apply(new QueryState(), $method, array_map(Domain::literal(...), $arguments));
        self::assertNotNull($state);
        $value = $state->get($field);
        self::assertSame($expected, ($value->soleArray()?->positional()[0] ?? $value)->soleLiteral()?->value);
        self::assertArrayNotHasKey('problem', $state->fields);
    }

    /**
     * @return iterable<array{string, list<string|int>, string, string|int}>
     */
    public static function providerClauseDispatch(): iterable
    {
        yield ['select', ['id'], 'columns', '"id"'];
        yield ['addselect', ['id'], 'columns', '"id"'];
        yield ['selectraw', ['id + 1'], 'columns', 'id + 1'];
        yield ['orderby', ['id'], 'orders', '"id" asc'];
        yield ['orderbydesc', ['id'], 'orders', '"id" desc'];
        yield ['orderbyraw', ['id desc'], 'orders', 'id desc'];
        yield ['groupby', ['id'], 'groups', '"id"'];
        yield ['havingraw', ['count(*) > 0'], 'having', 'count(*) > 0'];
        yield ['orhavingraw', ['count(*) > 0'], 'having', 'count(*) > 0'];
        yield ['having', ['total', '>', 1], 'having', '"total" > ?'];
        yield ['orhaving', ['total', 1], 'having', '"total" = ?'];
        yield ['latest', [], 'orders', '"created_at" desc'];
        yield ['oldest', ['id'], 'orders', '"id" asc'];
        yield ['from', ['posts'], 'table', 'posts'];
        yield ['limit', [0], 'limit', 0];
        yield ['take', [2], 'limit', 2];
        yield ['offset', [0], 'offset', 0];
        yield ['skip', [2], 'offset', 2];
        yield ['join', ['p', 'p.id', '=', 'u.id'], 'joins', 'inner join "p" on "p"."id" = "u"."id"'];
        yield ['leftjoin', ['p', 'p.id', '=', 'u.id'], 'joins', 'left join "p" on "p"."id" = "u"."id"'];
        yield ['rightjoin', ['p', 'p.id', '=', 'u.id'], 'joins', 'right join "p" on "p"."id" = "u"."id"'];
    }


    public function testFromSetsTheTableWithAnOptionalAlias(): void
    {
        $c = new Clauses(new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('sqlite')));
        self::assertSame('posts', $c->from(new QueryState(), [Domain::literal('posts')])->get('table')->soleLiteral()?->value);
        self::assertSame('posts as p', $c->from(new QueryState(), [Domain::literal('posts'), Domain::literal('p')])->get('table')->soleLiteral()?->value);
        self::assertSame('posts', $c->from(new QueryState(), [Domain::literal('posts'), Domain::literal(null)])->get('table')->soleLiteral()?->value);
        self::assertFalse($c->from(new QueryState(), [Domain::literal('posts'), Domain::unknown()])->get('table')->isExact());
        self::assertFalse($c->from(new QueryState(), [])->get('problem')->isExact());
        self::assertFalse($c->from(new QueryState(), [Domain::of(new ObjectTerm('Builder'))])->get('problem')->isExact());
        self::assertFalse($c->apply(new QueryState(), 'latest', [Domain::literal('a'), Domain::literal('b')])?->get('problem')->isExact());
    }

    public function testHavingCompilesComparisonsWithTheirBooleans(): void
    {
        $c = new Clauses(new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('mysql')));
        $state = $c->having(new QueryState(), [Domain::literal('total'), Domain::literal('>'), Domain::literal(100)], 'and');
        $state = $c->having($state, [Domain::literal('count'), Domain::literal(null)], 'or');
        self::assertSame(['`total` > ?', 'or `count` is null'], array_map(static fn (Domain $v): mixed => $v->soleLiteral()?->value, $state->items('having')));
        self::assertSame([100], array_map(static fn (Domain $v): mixed => $v->soleLiteral()?->value, $state->items('havingBindings')));
        self::assertFalse($c->having(new QueryState(), [], 'and')->get('problem')->isExact());
        self::assertFalse($c->having(new QueryState(), [QueryState::list([Domain::literal(1), Domain::literal(2)])], 'and')->get('problem')->isExact());
    }
}
