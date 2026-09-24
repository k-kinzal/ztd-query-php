<?php

declare(strict_types=1);

namespace Tests\Unit\Extension\Laravel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Evaluation\ArrayEntry;
use SqlCatalog\Evaluation\ArrayTerm;
use SqlCatalog\Evaluation\Domain;
use SqlCatalog\Evaluation\LiteralTerm;
use SqlCatalog\Evaluation\ObjectTerm;
use SqlCatalog\Evaluation\OpaqueTerm;
use SqlCatalog\Evaluation\PatternTerm;
use SqlCatalog\Extension\Laravel\Grammar;
use SqlCatalog\Extension\Laravel\QueryState;
use SqlCatalog\Extension\Laravel\WriteCompiler;
use SqlCatalog\Text\LiteralText;
use SqlCatalog\Text\TextGeneralization;
use SqlCatalog\Text\TextHole;
use SqlCatalog\Text\TextPattern;
use SqlCatalog\Type\TypeShape;

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
        $c = new WriteCompiler(new Grammar('sqlite'));
        $state = new QueryState(['table' => Domain::literal('users'), 'where' => QueryState::list([Domain::literal('id = ?')]), 'whereBindings' => QueryState::list([Domain::literal(1)])]);
        [$sql, $bindings] = $c->compile($state, 'delete', []);
        self::assertSame('delete from "users" where id = ?', $sql->soleLiteral()?->value);
        self::assertSame(1, $bindings->soleArray()?->positional()[0]->soleLiteral()?->value);
        self::assertFalse($c->compile($state->with('limit', Domain::literal(1)), 'delete', [])[0]->isExact());
        self::assertFalse($c->compile($state, 'upsert', [])[0]->isExact());
    }

    public function testUpdateOrdersAssignmentsBeforePredicateBindings(): void
    {
        $c = new WriteCompiler(new Grammar('mysql'));
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
        [$sql, $bindings] = (new WriteCompiler(new Grammar($dialect)))->insert($state, $row, true);
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
        $c = new WriteCompiler(new Grammar('sqlite'));
        $row = Domain::of(new ArrayTerm([new ArrayEntry(Domain::literal('z'), Domain::literal(1)), new ArrayEntry(Domain::literal('a'), Domain::literal(2))]));
        self::assertSame(['a', 'z'], array_keys($c->rows(new ArrayTerm([new ArrayEntry(null, $row)]))[0]));
        self::assertSame([], $c->rows(new ArrayTerm([new ArrayEntry(null, Domain::unknown())])));
    }

    public function testWhereDoesNotInventAnEmptyPredicate(): void
    {
        self::assertSame('', (new WriteCompiler(new Grammar('sqlite')))->where(new QueryState())->soleLiteral()?->value);
    }

    public function testUnknownProducesAnIncompleteSqlDomain(): void
    {
        [$sql, $bindings] = (new WriteCompiler(new Grammar('sqlite')))->unknown('custom write');
        self::assertFalse($sql->isExact());
        self::assertNull($bindings->soleArray());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerWriteDispatch')]
    public function testCompileSelectsTheWriteOperation(string $method, string $expected): void
    {
        $row = Domain::of(new ArrayTerm([new ArrayEntry(Domain::literal('id'), Domain::literal(7))]));
        [$sql, $bindings] = (new WriteCompiler(new Grammar('sqlite')))->compile(new QueryState(['table' => Domain::literal('users')]), $method, [$row]);
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
        $compiler = new WriteCompiler(new Grammar('sqlite'));
        self::assertFalse($compiler->compile($state, 'upsert', [$row])[0]->isExact());
        self::assertFalse($compiler->compile($state, 'insert', [$row, Domain::literal(1)])[0]->isExact());
        self::assertFalse($compiler->compile($state, 'update', [Domain::of(new ArrayTerm([], false))])[0]->isExact());
        self::assertFalse($compiler->compile($state->with('joins', QueryState::list([Domain::literal('join x')])), 'delete', [])[0]->isExact());
        self::assertFalse($compiler->compile($state->with('orders', QueryState::list([Domain::literal('id')])), 'delete', [])[0]->isExact());
    }

    public function testInsertRejectsBulkRowsWithDifferentColumns(): void
    {
        $rows = new ArrayTerm([new ArrayEntry(null, Domain::of(new ArrayTerm([new ArrayEntry(Domain::literal('a'), Domain::literal(1))]))), new ArrayEntry(null, Domain::of(new ArrayTerm([new ArrayEntry(Domain::literal('b'), Domain::literal(2))])))]);
        self::assertFalse((new WriteCompiler(new Grammar('sqlite')))->insert(new QueryState(['table' => Domain::literal('users')]), $rows, false)[0]->isExact());
    }

    public function testInsertSortsBothColumnNamesAndValuesAcrossBulkRows(): void
    {
        $row = Domain::of(new ArrayTerm([new ArrayEntry(Domain::literal('z'), Domain::literal(1)), new ArrayEntry(Domain::literal('a'), Domain::literal(2))]));
        $rows = new ArrayTerm([new ArrayEntry(null, $row), new ArrayEntry(null, $row)]);
        [$sql, $bindings] = (new WriteCompiler(new Grammar('sqlite')))->insert(new QueryState(['table' => Domain::literal('users')]), $rows, false);
        self::assertSame('insert into "users" ("a", "z") values (?, ?), (?, ?)', $sql->soleLiteral()?->value);
        self::assertSame([2, 1, 2, 1], array_map(static fn (Domain $value): mixed => $value->soleLiteral()?->value, $bindings->soleArray()?->positional() ?? []));
    }
}
