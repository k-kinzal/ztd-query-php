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
use SqlCatalog\Extension\Laravel\Grammar;
use SqlCatalog\Extension\Laravel\QueryState;
use SqlCatalog\Extension\Laravel\WriteCompiler;

#[CoversClass(WriteCompiler::class)]
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
final class WriteCompilerTest extends TestCase
{
    public function testCompileDeletesOnlyTheDerivedPredicateAndRejectsModifiers(): void
    {
        $c = new WriteCompiler(new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('sqlite')));
        $state = new QueryState(['table' => Domain::literal('users'), 'where' => QueryState::list([Domain::literal('id = ?')]), 'whereBindings' => QueryState::list([Domain::literal(1)])]);
        [$sql, $bindings] = $c->compile($state, 'delete', []);
        self::assertSame('delete from "users" where id = ?', $sql->soleLiteral()?->value);
        self::assertSame(1, $bindings->soleArray()?->positional()[0]->soleLiteral()?->value);
        self::assertFalse($c->compile($state->with('limit', Domain::literal(1)), 'delete', [])[0]->isExact());
        self::assertFalse($c->compile($state, 'upsert', [])[0]->isExact());
    }

    public function testUpdateOrdersAssignmentsBeforePredicateBindings(): void
    {
        $c = new WriteCompiler(new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('mysql')));
        $row = new ArrayTerm([new ArrayEntry(Domain::literal('name'), Domain::literal('Ada'))]);
        $state = new QueryState(['table' => Domain::literal('users'), 'where' => QueryState::list([Domain::literal('id = ?')]), 'whereBindings' => QueryState::list([Domain::literal(7)])]);
        [$sql, $bindings] = $c->update($state, $row);
        self::assertSame('update `users` set `name` = ? where id = ?', $sql->soleLiteral()?->value);
        self::assertSame(['Ada', 7], array_map(static fn (Domain $v): mixed => $v->soleLiteral()?->value, $bindings->soleArray()?->positional() ?? []));
        self::assertFalse($c->update($state, new ArrayTerm([]))[0]->isExact());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerInsertUsesEachDialectsIgnoreSyntaxAndPreservesColumnOrder')]
    public function testInsertUsesEachDialectsIgnoreSyntaxAndPreservesColumnOrder(string $dialect, string $expected): void
    {
        $state = new QueryState(['table' => Domain::literal('users')]);
        $row = new ArrayTerm([new ArrayEntry(Domain::literal('name'), Domain::literal('Ada')), new ArrayEntry(Domain::literal('id'), Domain::literal(7))]);
        [$sql, $bindings] = (new WriteCompiler(new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find($dialect))))->insert($state, $row, true);
        self::assertSame($expected, $sql->soleLiteral()?->value);
        self::assertSame(['Ada', 7], array_map(static fn (Domain $v): mixed => $v->soleLiteral()?->value, $bindings->soleArray()?->positional() ?? []));
    }

    /**
     * @return iterable<array{string, string}>
     */
    public static function providerInsertUsesEachDialectsIgnoreSyntaxAndPreservesColumnOrder(): iterable
    {
        foreach (['mysql' => 'insert ignore into `users` (`name`, `id`) values (?, ?)', 'sqlite' => 'insert or ignore into "users" ("name", "id") values (?, ?)', 'pgsql' => 'insert into "users" ("name", "id") values (?, ?) on conflict do nothing'] as $dialect => $expected) {
            yield [$dialect, $expected];
        }
    }

    public function testRowsSortsBulkKeysAndRejectsIncompleteRows(): void
    {
        $c = new WriteCompiler(new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('sqlite')));
        $row = Domain::of(new ArrayTerm([new ArrayEntry(Domain::literal('z'), Domain::literal(1)), new ArrayEntry(Domain::literal('a'), Domain::literal(2))]));
        self::assertSame(['a', 'z'], array_keys($c->rows(new ArrayTerm([new ArrayEntry(null, $row)]))[0]));
        self::assertSame([], $c->rows(new ArrayTerm([new ArrayEntry(null, Domain::unknown())])));
    }

    public function testWhereDoesNotInventAnEmptyPredicate(): void
    {
        self::assertSame('', (new WriteCompiler(new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('sqlite'))))->where(new QueryState())->soleLiteral()?->value);
    }

    public function testUnknownProducesAnIncompleteSqlDomain(): void
    {
        [$sql, $bindings] = (new WriteCompiler(new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('sqlite'))))->unknown('custom write');
        self::assertFalse($sql->isExact());
        self::assertNull($bindings->soleArray());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerWriteDispatch')]
    public function testCompileSelectsTheWriteOperation(string $method, string $expected): void
    {
        $row = Domain::of(new ArrayTerm([new ArrayEntry(Domain::literal('id'), Domain::literal(7))]));
        [$sql, $bindings] = (new WriteCompiler(new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('sqlite'))))->compile(new QueryState(['table' => Domain::literal('users')]), $method, [$row]);
        self::assertSame($expected, $sql->soleLiteral()?->value);
        self::assertSame(7, $bindings->soleArray()?->positional()[0]->soleLiteral()?->value);
    }

    /**
     * @return iterable<array{string, string}>
     */
    public static function providerWriteDispatch(): iterable
    {
        yield ['insert', 'insert into "users" ("id") values (?)'];
        yield ['insertorignore', 'insert or ignore into "users" ("id") values (?)'];
        yield ['update', 'update "users" set "id" = ?'];
    }

    public function testCompileDoesNotInterpretACompleteRowAsAnUnknownWriteOperation(): void
    {
        $row = Domain::of(new ArrayTerm([new ArrayEntry(Domain::literal('id'), Domain::literal(7))]));
        $state = new QueryState(['table' => Domain::literal('users')]);
        $compiler = new WriteCompiler(new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('sqlite')));
        self::assertFalse($compiler->compile($state, 'upsert', [$row])[0]->isExact());
        self::assertFalse($compiler->compile($state, 'insert', [$row, Domain::literal(1)])[0]->isExact());
        self::assertFalse($compiler->compile($state, 'update', [Domain::of(new ArrayTerm([], false))])[0]->isExact());
        self::assertFalse($compiler->compile($state->with('joins', QueryState::list([Domain::literal('join x')])), 'delete', [])[0]->isExact());
        self::assertFalse($compiler->compile($state->with('orders', QueryState::list([Domain::literal('id')])), 'delete', [])[0]->isExact());
    }

    public function testInsertRejectsBulkRowsWithDifferentColumns(): void
    {
        $rows = new ArrayTerm([new ArrayEntry(null, Domain::of(new ArrayTerm([new ArrayEntry(Domain::literal('a'), Domain::literal(1))]))), new ArrayEntry(null, Domain::of(new ArrayTerm([new ArrayEntry(Domain::literal('b'), Domain::literal(2))])))]);
        self::assertFalse((new WriteCompiler(new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('sqlite'))))->insert(new QueryState(['table' => Domain::literal('users')]), $rows, false)[0]->isExact());
    }

    public function testInsertSortsBothColumnNamesAndValuesAcrossBulkRows(): void
    {
        $row = Domain::of(new ArrayTerm([new ArrayEntry(Domain::literal('z'), Domain::literal(1)), new ArrayEntry(Domain::literal('a'), Domain::literal(2))]));
        $rows = new ArrayTerm([new ArrayEntry(null, $row), new ArrayEntry(null, $row)]);
        [$sql, $bindings] = (new WriteCompiler(new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('sqlite'))))->insert(new QueryState(['table' => Domain::literal('users')]), $rows, false);
        self::assertSame('insert into "users" ("a", "z") values (?, ?), (?, ?)', $sql->soleLiteral()?->value);
        self::assertSame([2, 1, 2, 1], array_map(static fn (Domain $value): mixed => $value->soleLiteral()?->value, $bindings->soleArray()?->positional() ?? []));
    }


    public function testStepWritesLaravelsRawArithmeticBeforeExtraColumns(): void
    {
        $compiler = new WriteCompiler(new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('mysql')));
        $state = new QueryState(['table' => Domain::literal('users'), 'where' => QueryState::list([Domain::literal('`id` = ?')]), 'whereBindings' => QueryState::list([Domain::literal(7)])]);
        $extra = Domain::of(new ArrayTerm([new ArrayEntry(Domain::literal('seen'), Domain::literal('now'))]));
        [$sql, $bindings] = $compiler->step($state, 'increment', [Domain::literal('logins'), Domain::literal(2), $extra]);
        self::assertSame('update `users` set `logins` = `logins` + 2, `seen` = ? where `id` = ?', $sql->soleLiteral()?->value);
        self::assertSame(['now', 7], array_map(static fn (Domain $v): mixed => $v->soleLiteral()?->value, $bindings->soleArray()?->positional() ?? []));
        self::assertSame('update `users` set `logins` = `logins` - 1 where `id` = ?', $compiler->step($state, 'decrement', [Domain::literal('logins')])[0]->soleLiteral()?->value);
        self::assertSame('update `users` set `logins` = `logins` + {$} where `id` = ?', $compiler->step($state, 'increment', [Domain::literal('logins'), Domain::unknown()])[0]->patterns()[0]->display());
        self::assertFalse($compiler->step($state, 'increment', [Domain::unknown()])[0]->isExact());
        self::assertFalse($compiler->step($state, 'increment', [Domain::literal('logins'), QueryState::list([])])[0]->isExact());
        self::assertFalse($compiler->compile($state, 'increment', [Domain::literal('logins'), Domain::literal(1), Domain::unknown()])[0]->isExact());
    }

    public function testInsertGetIdReturnsTheKeyOnlyWhereTheDialectAsksForIt(): void
    {
        $state = new QueryState(['table' => Domain::literal('users')]);
        $row = new ArrayTerm([new ArrayEntry(Domain::literal('name'), Domain::literal('Ada'))]);
        $pgsql = new WriteCompiler(new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('pgsql')));
        self::assertSame('insert into "users" ("name") values (?) returning "id"', $pgsql->insertGetId($state, $row, Domain::literal(null))[0]->soleLiteral()?->value);
        self::assertSame('insert into "users" ("name") values (?) returning "uid"', $pgsql->insertGetId($state, $row, Domain::literal('uid'))[0]->soleLiteral()?->value);
        $mysql = new WriteCompiler(new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('mysql')));
        self::assertSame('insert into `users` (`name`) values (?)', $mysql->compile($state, 'insertgetid', [Domain::of($row)])[0]->soleLiteral()?->value);
        self::assertSame(['Ada'], array_map(static fn (Domain $v): mixed => $v->soleLiteral()?->value, $mysql->insertGetId($state, $row, Domain::literal(null))[1]->soleArray()?->positional() ?? []));
        self::assertFalse($mysql->insertGetId($state, new ArrayTerm([new ArrayEntry(null, Domain::of($row))]), Domain::literal(null))[0]->isExact());
    }

    public function testCompileDeletesOneRowByItsKey(): void
    {
        $compiler = new WriteCompiler(new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('sqlite')));
        $state = new QueryState(['table' => Domain::literal('users'), 'key' => Domain::literal('id')]);
        [$sql, $bindings] = $compiler->compile($state, 'delete', [Domain::literal(7)]);
        self::assertSame('delete from "users" where "id" = ?', $sql->soleLiteral()?->value);
        self::assertSame([7], array_map(static fn (Domain $v): mixed => $v->soleLiteral()?->value, $bindings->soleArray()?->positional() ?? []));
        self::assertFalse($compiler->compile($state, 'delete', [QueryState::list([])])[0]->isExact());
    }
}
