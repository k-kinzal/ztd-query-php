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
use SqlCatalog\Extension\Laravel\Predicates;
use SqlCatalog\Extension\Laravel\QueryState;
use SqlCatalog\Extension\Laravel\SelectCompiler;

#[CoversClass(SelectCompiler::class)]
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
#[UsesClass(Clauses::class)]
#[UsesClass(Predicates::class)]
final class SelectCompilerTest extends TestCase
{
    public function testCompileFindUsesTheQualifiedKeyAndOneRowLimit(): void
    {
        $state = new QueryState(['table' => Domain::literal('users'), 'key' => Domain::literal('users.id')]);
        [$sql, $bindings] = (new SelectCompiler(new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('sqlite'))))->compile($state, 'find', [Domain::literal(7)]);
        self::assertSame('select * from "users" where "users"."id" = ? limit 1', $sql->soleLiteral()?->value);
        self::assertSame(7, $bindings->soleArray()?->positional()[0]->soleLiteral()?->value);
    }

    public function testCompilePreservesExplicitProjectionAndWrapsExists(): void
    {
        $compiler = new SelectCompiler(new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('mysql')));
        $state = (new Clauses(new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('mysql'))))->select(new QueryState(['table' => Domain::literal('users')]), [Domain::literal('id')]);
        self::assertSame('select `id` from `users` limit 1', $compiler->compile($state, 'first', [QueryState::list([Domain::literal('name')])])[0]->soleLiteral()?->value);
        self::assertSame('select exists(select `id` from `users`) as `exists`', $compiler->compile($state, 'exists', [])[0]->soleLiteral()?->value);
        self::assertFalse($compiler->compile($state, 'get', [Domain::literal('a'), Domain::literal('b')])[0]->isExact());
        self::assertSame('select `id` from `users` where 0 = 1', $compiler->compile($state->with('key', Domain::literal('users.id'))->with('model', Domain::literal('User')), 'find', [QueryState::list([])])[0]->soleLiteral()?->value);
        self::assertFalse($compiler->compile($state->with('key', Domain::literal('id')), 'find', [QueryState::list([])])[0]->isExact());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerSelectPreservesExplicitOffsetsAndComponentBindingOrder')]
    public function testSelectPreservesExplicitOffsetsAndComponentBindingOrder(string $dialect, int $offset, string $expected): void
    {
        $state = new QueryState(['table' => Domain::literal('users'), 'columns' => QueryState::list([Domain::literal('? as x')]), 'selectBindings' => QueryState::list([Domain::literal(1)]), 'where' => QueryState::list([Domain::literal('id > ?')]), 'whereBindings' => QueryState::list([Domain::literal(2)]), 'offset' => Domain::literal($offset)]);
        [$sql, $bindings] = (new SelectCompiler(new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find($dialect))))->select($state);
        self::assertSame($expected, $sql->soleLiteral()?->value);
        self::assertSame([1, 2], array_map(static fn (Domain $v): mixed => $v->soleLiteral()?->value, $bindings->soleArray()?->positional() ?? []));
    }

    /**
     * @return iterable<array{string, int, string}>
     */
    public static function providerSelectPreservesExplicitOffsetsAndComponentBindingOrder(): iterable
    {
        foreach (['mysql' => '`users`', 'sqlite' => '"users"', 'pgsql' => '"users"'] as $dialect => $table) {
            yield [$dialect, 0, 'select ? as x from ' . $table . ' where id > ? offset 0'];
            yield [$dialect, 3, 'select ? as x from ' . $table . ' where id > ? offset 3'];
        }
    }

    public function testAggregateQuotesTheResultAliasAndDiscardsProjectionBindings(): void
    {
        $state = new QueryState(['table' => Domain::literal('users'), 'selectBindings' => QueryState::list([Domain::literal(1)]), 'orders' => QueryState::list([Domain::literal('id desc')])]);
        $compiler = new SelectCompiler(new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('sqlite')));
        [$sql, $bindings] = $compiler->aggregate($state, 'count', []);
        self::assertSame('select count(*) as "aggregate" from "users"', $sql->soleLiteral()?->value);
        self::assertSame([], $bindings->soleArray()?->entries);
        self::assertSame('select count(*) as "aggregate" from "users"', $compiler->aggregate($state->with('distinct', Domain::literal(true)), 'count', [])[0]->soleLiteral()?->value);
        self::assertSame('select count(distinct "id") as "aggregate" from "users"', $compiler->aggregate($state->with('distinct', Domain::literal(true)), 'count', [Domain::literal('id')])[0]->soleLiteral()?->value);
    }

    /**
     * @param list<Domain> $arguments
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerReadOperations')]
    public function testCompileHonorsTerminalColumnsAndAggregateArguments(string $method, array $arguments, string $expected): void
    {
        $state = new QueryState(['table' => Domain::literal('users'), 'key' => Domain::literal('id')]);
        [$sql, $bindings] = (new SelectCompiler(new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('sqlite'))))->compile($state, $method, $arguments);
        self::assertSame($expected, $sql->soleLiteral()?->value);
        self::assertNotNull($bindings->soleArray());
    }

    /**
     * @return iterable<array{string, list<Domain>, string}>
     */
    public static function providerReadOperations(): iterable
    {
        yield ['get', [QueryState::list([Domain::literal('name')])], 'select "name" from "users"'];
        yield ['first', [QueryState::list([Domain::literal('name')])], 'select "name" from "users" limit 1'];
        yield ['firstorfail', [], 'select * from "users" limit 1'];
        yield ['find', [Domain::literal(1), QueryState::list([Domain::literal('name')])], 'select "name" from "users" where "id" = ? limit 1'];
        yield ['pluck', [Domain::literal('name'), Domain::literal('id')], 'select "name", "id" from "users"'];
        yield ['doesntexist', [], 'select exists(select * from "users") as "exists"'];
        yield ['findorfail', [Domain::literal(1)], 'select * from "users" where "id" = ? limit 1'];
        yield ['sole', [], 'select * from "users" limit 2'];
        yield ['value', [Domain::literal('email')], 'select "email" from "users" limit 1'];
        yield ['cursor', [], 'select * from "users"'];
        foreach (['count', 'sum', 'avg', 'min', 'max'] as $method) {
            yield [$method, [Domain::literal('id')], 'select '.$method.'("id") as "aggregate" from "users"'];
        }
    }

    public function testSelectAssemblesAllComponentsAndBindingGroupsInSqlOrder(): void
    {
        $state = new QueryState(['table' => Domain::literal('users'), 'columns' => QueryState::list([Domain::literal('? as a')]), 'joins' => QueryState::list([Domain::literal('join teams on teams.id = users.team_id')]), 'where' => QueryState::list([Domain::literal('active = ?')]), 'groups' => QueryState::list([Domain::literal('a'), Domain::literal('b')]), 'having' => QueryState::list([Domain::literal('count(*) > ?'), Domain::literal('and count(*) < ?')]), 'orders' => QueryState::list([Domain::literal('a asc'), Domain::literal('b desc')]), 'distinct' => Domain::literal(true), 'offset' => Domain::literal(0), 'selectBindings' => QueryState::list([Domain::literal(1)]), 'joinBindings' => QueryState::list([Domain::literal(2)]), 'whereBindings' => QueryState::list([Domain::literal(3)]), 'havingBindings' => QueryState::list([Domain::literal(4)]), 'orderBindings' => QueryState::list([Domain::literal(5)])]);
        [$sql, $bindings] = (new SelectCompiler(new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('sqlite'))))->select($state);
        self::assertSame('select distinct ? as a from "users" join teams on teams.id = users.team_id where active = ? group by a, b having count(*) > ? and count(*) < ? order by a asc, b desc offset 0', $sql->soleLiteral()?->value);
        self::assertSame([1, 2, 3, 4, 5], array_map(static fn (Domain $value): mixed => $value->soleLiteral()?->value, $bindings->soleArray()?->positional() ?? []));
    }

    public function testCompileKeepsMissingFindKeysAndExcessPluckArgumentsOpen(): void
    {
        $compiler = new SelectCompiler(new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('sqlite')));
        $state = new QueryState(['table' => Domain::literal('users')]);
        self::assertFalse($compiler->compile($state, 'find', [])[0]->isExact());
        self::assertFalse($compiler->compile($state, 'pluck', array_map(Domain::literal(...), ['a', 'b', 'c']))[0]->isExact());
        self::assertFalse($compiler->aggregate($state->with('having', QueryState::list([Domain::literal('count(*) > 1')])), 'count', [])[0]->isExact());
    }


    public function testKeyComparesScalarsAndListsAgainstThePrimaryKey(): void
    {
        $compiler = new SelectCompiler(new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('sqlite')));
        $state = new QueryState(['table' => Domain::literal('users'), 'key' => Domain::literal('users.id'), 'model' => Domain::literal('User')]);
        $one = $compiler->key($state, Domain::literal(7));
        self::assertSame('"users"."id" = ?', $one->items('where')[0]->soleLiteral()?->value);
        self::assertSame(1, $one->get('limit')->soleLiteral()?->value);
        $many = $compiler->key($state, QueryState::list([Domain::literal(1), Domain::literal('2')]));
        self::assertSame('"users"."id" in (1, 2)', $many->items('where')[0]->soleLiteral()?->value);
        self::assertSame([], $many->items('whereBindings'));
        self::assertNull($many->get('limit')->soleLiteral()?->value);
        self::assertSame('0 = 1', $compiler->key($state, QueryState::list([]))->items('where')[0]->soleLiteral()?->value);
        self::assertFalse($compiler->key($state, QueryState::list([Domain::unknown()]))->items('where')[0]->isExact());
        $strings = $compiler->key($state->with('keyType', Domain::literal('string')), QueryState::list([Domain::literal('a'), Domain::literal('b')]));
        self::assertSame('"users"."id" in (?, ?)', $strings->items('where')[0]->soleLiteral()?->value);
        self::assertSame(['a', 'b'], array_map(static fn (Domain $v): mixed => $v->soleLiteral()?->value, $strings->items('whereBindings')));
        $typed = $compiler->key($state, Domain::opaque(TypeShape::of(['array']), \SqlCatalog\Core\Text\Origin::Parameter, '$ids'));
        self::assertFalse($typed->items('where')[0]->isExact());
        self::assertSame([], $typed->items('whereBindings'));
        self::assertFalse($compiler->key(new QueryState(['key' => Domain::literal('id')]), QueryState::list([Domain::literal(1)]))->get('problem')->isExact());
    }

    public function testPageWindowsChunksByTheLoopAndPagesByTheRequest(): void
    {
        $compiler = new SelectCompiler(new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('sqlite')));
        $state = new QueryState(['table' => Domain::literal('users')]);
        $chunk = $compiler->page($state, 'chunk', [Domain::literal(100)]);
        self::assertSame(100, $chunk->get('limit')->soleLiteral()?->value);
        self::assertSame(\SqlCatalog\Core\Text\Origin::Loop, $chunk->get('offset')->patterns()[0]->holes()[0]->origin);
        $page = $compiler->page($state, 'paginate', []);
        self::assertSame(15, $page->get('limit')->soleLiteral()?->value);
        self::assertSame(\SqlCatalog\Core\Text\Origin::External, $page->get('offset')->patterns()[0]->holes()[0]->origin);
        self::assertSame(21, $compiler->page($state, 'simplepaginate', [Domain::literal(20)])->get('limit')->soleLiteral()?->value);
        self::assertSame(8, $compiler->page($state->with('perPage', Domain::literal(7)), 'simplepaginate', [Domain::literal(null)])->get('limit')->soleLiteral()?->value);
        self::assertSame(7, $compiler->page($state->with('perPage', Domain::literal(7)), 'paginate', [])->get('limit')->soleLiteral()?->value);
        self::assertSame(1000, $compiler->page($state, 'lazy', [])->get('limit')->soleLiteral()?->value);
        self::assertSame(1000, $compiler->page($state, 'each', [Domain::unknown()])->get('limit')->soleLiteral()?->value);
        self::assertSame(50, $compiler->page($state, 'each', [Domain::unknown(), Domain::literal(50)])->get('limit')->soleLiteral()?->value);
        self::assertSame('select * from "users" limit 1000 offset {$}', $compiler->compile($state, 'lazy', [])[0]->patterns()[0]->display());
        self::assertFalse($compiler->page($state, 'simplepaginate', [Domain::unknown()])->get('limit')->isExact());
        [$sql] = $compiler->compile($state, 'paginate', [Domain::literal(10), QueryState::list([Domain::literal('id')])]);
        self::assertSame('select "id" from "users" limit 10 offset {$}', $sql->patterns()[0]->display());
    }

    public function testTotalCountsWithoutTheWindowOrOrdering(): void
    {
        $compiler = new SelectCompiler(new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('sqlite')));
        $state = new QueryState(['table' => Domain::literal('users'), 'columns' => QueryState::list([Domain::literal('"id"')]), 'where' => QueryState::list([Domain::literal('"a" = ?')]), 'whereBindings' => QueryState::list([Domain::literal(1)]), 'orders' => QueryState::list([Domain::literal('"id" asc')]), 'limit' => Domain::literal(10), 'offset' => Domain::literal(20)]);
        [$sql, $bindings] = $compiler->total($state);
        self::assertSame('select count(*) as "aggregate" from "users" where "a" = ?', $sql->soleLiteral()?->value);
        self::assertSame([1], array_map(static fn (Domain $v): mixed => $v->soleLiteral()?->value, $bindings->soleArray()?->positional() ?? []));
        self::assertSame('select count(*) as "aggregate" from (select "id" from "users" where "a" = ? group by "a") as "aggregate_table"', $compiler->total($state->with('groups', QueryState::list([Domain::literal('"a"')])))[0]->soleLiteral()?->value);
        self::assertSame('select count(*) as "aggregate" from (select * from "users" where "a" = ? having "x" > ?) as "aggregate_table"', $compiler->total($state->with('columns', QueryState::list([]))->with('having', QueryState::list([Domain::literal('"x" > ?')])))[0]->soleLiteral()?->value);
        self::assertSame('select count(*) as "aggregate" from (select "users".* from "users" inner join "t" on "t"."u" = "users"."id" where "a" = ? group by "a") as "aggregate_table"', $compiler->total($state->with('columns', QueryState::list([]))->with('joins', QueryState::list([Domain::literal('inner join "t" on "t"."u" = "users"."id"')]))->with('groups', QueryState::list([Domain::literal('"a"')])))[0]->soleLiteral()?->value);
        self::assertSame('select count(*) as "aggregate" from "users" where "a" = ?', $compiler->total($state->with('distinct', Domain::literal(true)))[0]->soleLiteral()?->value);
    }

    public function testSelectWritesUnresolvedLimitsAndOffsetsAsGaps(): void
    {
        $compiler = new SelectCompiler(new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('mysql')));
        $state = new QueryState(['table' => Domain::literal('users'), 'columns' => QueryState::list([Domain::literal('*')]), 'limit' => Domain::opaque(TypeShape::of(['int']), \SqlCatalog\Core\Text\Origin::Parameter, '$n'), 'offset' => Domain::literal(null)]);
        [$sql] = $compiler->select($state);
        self::assertSame('select * from `users` limit {$}', $sql->patterns()[0]->display());
        self::assertSame(\SqlCatalog\Core\Text\Origin::Parameter, $sql->patterns()[0]->holes()[0]->origin);
        self::assertCount(1, $sql->patterns()[0]->holes());
    }
}
