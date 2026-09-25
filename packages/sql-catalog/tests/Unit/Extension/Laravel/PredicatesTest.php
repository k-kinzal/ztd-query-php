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
use SqlCatalog\Extension\Laravel\Predicates;
use SqlCatalog\Extension\Laravel\QueryState;

#[CoversClass(Predicates::class)]
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
final class PredicatesTest extends TestCase
{
    public function testApplyDispatchesOrPredicatesAndRejectsOtherOperations(): void
    {
        $predicates = new Predicates(new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('sqlite')));
        $state = $predicates->basic(new QueryState(), [Domain::literal('id'), Domain::literal(1)], 'and');
        $state = $predicates->apply($state, 'orwhere', [Domain::literal('id'), Domain::literal(2)]);
        self::assertSame('or "id" = ?', $state?->items('where')[1]->soleLiteral()?->value);
        self::assertNull($predicates->apply(new QueryState(), 'unknown', []));
    }

    public function testAddDoesNotPrefixTheFirstPredicateWithABoolean(): void
    {
        $p = new Predicates(new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('sqlite')));
        $state = $p->add(new QueryState(), Domain::literal('a = ?'), [Domain::literal(1)], 'or');
        $state = $p->add($state, Domain::literal('b = ?'), [Domain::literal(2)]);
        self::assertSame(['a = ?', 'and b = ?'], array_map(static fn (Domain $v): mixed => $v->soleLiteral()?->value, $state->items('where')));
        self::assertCount(2, $state->items('whereBindings'));
    }

    public function testBasicNormalizesNullAndPreservesComparisonBindings(): void
    {
        $p = new Predicates(new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('sqlite')));
        $state = $p->basic(new QueryState(), [Domain::literal('id'), Domain::literal('>'), Domain::literal(2)], 'and');
        self::assertSame('"id" > ?', $state->items('where')[0]->soleLiteral()?->value);
        self::assertSame(2, $state->items('whereBindings')[0]->soleLiteral()?->value);
        $null = $p->basic(new QueryState(), [Domain::literal('id'), Domain::literal('!='), Domain::literal(null)], 'and');
        self::assertSame('"id" is not null', $null->items('where')[0]->soleLiteral()?->value);
        self::assertSame([], $null->items('whereBindings'));
        $open = $p->basic(new QueryState(), [Domain::literal('id'), Domain::unknown()], 'and');
        self::assertSame('"id" = ?', $open->items('where')[0]->soleLiteral()?->value);
        self::assertFalse($open->items('whereBindings')[0]->isExact());
        self::assertArrayNotHasKey('problem', $open->fields);
        self::assertFalse($p->basic(new QueryState(), [], 'and')->get('problem')->isExact());
        self::assertFalse($p->basic(new QueryState(), [Domain::literal('id'), Domain::literal('bad'), Domain::literal(1)], 'and')->get('problem')->isExact());
    }

    public function testNullsSupportsMultipleColumnsWithoutBindings(): void
    {
        $p = new Predicates(new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('sqlite')));
        $state = $p->nulls(new QueryState(), [QueryState::list([Domain::literal('a'), Domain::literal('b')])], 'or', false);
        self::assertSame('"a" is null', $state->items('where')[0]->soleLiteral()?->value);
        self::assertSame('or "b" is null', $state->items('where')[1]->soleLiteral()?->value);
        self::assertFalse($p->nulls(new QueryState(), [], 'and', false)->get('problem')->isExact());
    }

    public function testInHandlesEmptySetsAndOrderedValues(): void
    {
        $p = new Predicates(new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('sqlite')));
        $empty = [Domain::literal('id'), QueryState::list([])];
        self::assertSame('0 = 1', $p->in(new QueryState(), $empty, 'and', false)->items('where')[0]->soleLiteral()?->value);
        self::assertSame('1 = 1', $p->in(new QueryState(), $empty, 'and', true)->items('where')[0]->soleLiteral()?->value);
        $state = $p->in(new QueryState(), [Domain::literal('id'), QueryState::list([Domain::literal(2), Domain::literal(1)])], 'and', true);
        self::assertSame('"id" not in (?, ?)', $state->items('where')[0]->soleLiteral()?->value);
        self::assertSame(2, $state->items('whereBindings')[0]->soleLiteral()?->value);
        $open = $p->in(new QueryState(), [Domain::literal('id'), Domain::unknown()], 'and', false);
        self::assertArrayNotHasKey('problem', $open->fields);
        self::assertFalse($open->items('where')[0]->isExact());
        self::assertSame('"id" in ({$})', $open->items('where')[0]->patterns()[0]->display());
        self::assertSame([], $open->items('whereBindings'));
        self::assertFalse($p->in(new QueryState(), [Domain::literal('id')], 'and', false)->get('problem')->isExact());
    }

    public function testBetweenKeepsBothBoundsInOrder(): void
    {
        $p = new Predicates(new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('mysql')));
        $state = $p->between(new QueryState(), [Domain::literal('id'), QueryState::list([Domain::literal(2), Domain::literal(9)])], 'and', true);
        self::assertSame('`id` not between ? and ?', $state->items('where')[0]->soleLiteral()?->value);
        self::assertSame([2, 9], array_map(static fn (Domain $v): mixed => $v->soleLiteral()?->value, $state->items('whereBindings')));
        self::assertFalse($p->between(new QueryState(), [], 'and', false)->get('problem')->isExact());
    }

    public function testColumnDoesNotTurnTheRightIdentifierIntoABinding(): void
    {
        $p = new Predicates(new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('sqlite')));
        $state = $p->column(new QueryState(), [Domain::literal('a'), Domain::literal('<'), Domain::literal('b')], 'and');
        self::assertSame('"a" < "b"', $state->items('where')[0]->soleLiteral()?->value);
        self::assertSame([], $state->items('whereBindings'));
        self::assertFalse($p->column(new QueryState(), [], 'and')->get('problem')->isExact());
        self::assertFalse($p->column(new QueryState(), [Domain::literal('a'), Domain::unknown(), Domain::literal('b')], 'and')->get('problem')->isExact());
    }

    public function testRawKeepsBindingsAndLeavesIncompleteArraysOpen(): void
    {
        $p = new Predicates(new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('sqlite')));
        $state = $p->raw(new QueryState(), [Domain::literal('id > ?'), QueryState::list([Domain::literal(2)])], 'and');
        self::assertSame('id > ?', $state->items('where')[0]->soleLiteral()?->value);
        self::assertSame(2, $state->items('whereBindings')[0]->soleLiteral()?->value);
        $counted = $p->raw(new QueryState(), [Domain::literal('a = ? and b = ?'), Domain::of(new ArrayTerm([], false))], 'and');
        self::assertArrayNotHasKey('problem', $counted->fields);
        self::assertCount(2, $counted->items('whereBindings'));
        self::assertFalse($p->raw(new QueryState(), [Domain::unknown(), Domain::of(new ArrayTerm([], false))], 'and')->get('problem')->isExact());
        self::assertFalse($p->raw(new QueryState(), [], 'and')->get('problem')->isExact());
    }

    public function testInPreservesValuesFromAssociativeArraysAndRejectsNestedArrays(): void
    {
        $p = new Predicates(new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('sqlite')));
        $values = Domain::of(new ArrayTerm([new ArrayEntry(Domain::literal('key'), Domain::literal(7))]));
        $state = $p->in(new QueryState(), [Domain::literal('id'), $values], 'and', false);
        self::assertSame('"id" in (?)', $state->items('where')[0]->soleLiteral()?->value);
        self::assertSame(7, $state->items('whereBindings')[0]->soleLiteral()?->value);
        self::assertFalse($p->in(new QueryState(), [Domain::literal('id'), QueryState::list([$values])], 'and', false)->get('problem')->isExact());
    }

    public function testNullsDoesNotDropUnknownColumnsOrAssociativeValues(): void
    {
        $p = new Predicates(new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('sqlite')));
        self::assertFalse($p->nulls(new QueryState(), [Domain::of(new ArrayTerm([], false))], 'and', false)->get('problem')->isExact());
        $state = $p->nulls(new QueryState(), [Domain::of(new ArrayTerm([new ArrayEntry(Domain::literal('key'), Domain::literal('id'))]))], 'and', false);
        self::assertSame('"id" is null', $state->items('where')[0]->soleLiteral()?->value);
    }

    public function testGroupDisjunctionPreservesConjunctionsWithoutAddingParentheses(): void
    {
        $state = new QueryState(['where' => QueryState::list([Domain::literal('a = ?'), Domain::literal('and b = ?')])]);
        self::assertSame($state, (new Predicates(new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('sqlite'))))->groupDisjunction($state));
        $disjunction = $state->with('where', QueryState::list([Domain::literal('a = ?'), Domain::literal('or b = ?')]));
        self::assertSame('(a = ? or b = ?)', (new Predicates(new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('sqlite'))))->groupDisjunction($disjunction)->items('where')[0]->soleLiteral()?->value);
    }

    /**
     * @param list<Domain> $arguments
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerPredicateDispatch')]
    public function testApplyPreservesThePredicateAndBoolean(string $method, array $arguments, string $expected, int $bindings): void
    {
        $state = new QueryState(['where' => QueryState::list([Domain::literal('active = 1')])]);
        $after = (new Predicates(new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('sqlite'))))->apply($state, $method, $arguments);
        self::assertNotNull($after);
        self::assertSame($expected, $after->items('where')[1]->soleLiteral()?->value);
        self::assertCount($bindings, $after->items('whereBindings'));
        self::assertArrayNotHasKey('problem', $after->fields);
    }

    /**
     * @return iterable<array{string, list<Domain>, string, int}>
     */
    public static function providerPredicateDispatch(): iterable
    {
        foreach (['where' => [' = ?', 1], 'wherenull' => [' is null', 0], 'wherenotnull' => [' is not null', 0], 'wherein' => [' in (?, ?)', 2], 'wherenotin' => [' not in (?, ?)', 2], 'wherebetween' => [' between ? and ?', 2], 'wherenotbetween' => [' not between ? and ?', 2], 'wherecolumn' => [' = "other"', 0], 'whereraw' => [' > ?', 1]] as $method => [$suffix, $count]) {
            $args = match ($method) {
                'where' => [Domain::literal('id'), Domain::literal(1)],
                'wherenull', 'wherenotnull' => [Domain::literal('id')],
                'wherecolumn' => [Domain::literal('id'), Domain::literal('other')],
                'whereraw' => [Domain::literal('"id" > ?'), QueryState::list([Domain::literal(1)])],
                'wherein', 'wherenotin', 'wherebetween', 'wherenotbetween' => [Domain::literal('id'), QueryState::list([Domain::literal(1), Domain::literal(2)])],
            };
            yield [$method, $args, 'and "id"'.$suffix, $count];
            yield ['or'.$method, $args, 'or "id"'.$suffix, $count];
        }
    }


    public function testBasicCompilesUnresolvedAndNullableValuesAsPlaceholders(): void
    {
        $p = new Predicates(new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('mysql')));
        $nullable = Domain::opaque(TypeShape::of(['int', 'null']), \SqlCatalog\Core\Text\Origin::Parameter, '$id');
        $state = $p->basic(new QueryState(), [Domain::literal('id'), $nullable], 'and');
        self::assertSame('`id` = ?', $state->items('where')[0]->soleLiteral()?->value);
        self::assertSame('int|null', $state->items('whereBindings')[0]->type()->display());
        $or = $p->basic($state, [Domain::literal('a'), Domain::literal('>'), Domain::literal(1), Domain::literal('OR')], 'and');
        self::assertSame('or `a` > ?', $or->items('where')[1]->soleLiteral()?->value);
        self::assertFalse($p->basic($state, [Domain::literal('a'), Domain::literal('>'), Domain::literal(1), Domain::unknown()], 'and')->get('problem')->isExact());
        self::assertFalse($p->basic(new QueryState(), [Domain::literal('id'), QueryState::list([Domain::literal(1)])], 'and')->get('problem')->isExact());
    }

    public function testGroupNestsArrayEntriesInParenthesesWithTheOuterBoolean(): void
    {
        $p = new Predicates(new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('sqlite')));
        $entries = new ArrayTerm([new ArrayEntry(Domain::literal('a'), Domain::literal(1)), new ArrayEntry(Domain::literal('b'), Domain::literal(null)), new ArrayEntry(null, QueryState::list([Domain::literal('c'), Domain::literal('>'), Domain::literal(2)]))]);
        $state = $p->basic(new QueryState(), [Domain::literal('x'), Domain::literal(0)], 'and');
        $state = $p->group($state, $entries, 'or');
        self::assertSame('or ("a" = ? or "b" is null and "c" > ?)', $state->items('where')[1]->soleLiteral()?->value);
        self::assertSame([0, 1, 2], array_map(static fn (Domain $v): mixed => $v->soleLiteral()?->value, $state->items('whereBindings')));
        self::assertSame($state, $p->group($state, new ArrayTerm([]), 'and'));
        self::assertFalse($p->group(new QueryState(), new ArrayTerm([], false), 'and')->get('problem')->isExact());
        self::assertFalse($p->group(new QueryState(), new ArrayTerm([new ArrayEntry(Domain::literal('a'), QueryState::list([]))]), 'and')->get('problem')->isExact());
    }

    public function testEntryReadsKeyedComparisonsAndPositionalArgumentLists(): void
    {
        $p = new Predicates(new Grammar(\SqlCatalog\Facade\Builtins::dialects()->find('sqlite')));
        self::assertSame('"a" = ?', $p->entry(new QueryState(), new ArrayEntry(Domain::literal('a'), Domain::literal(1)), 'and')->items('where')[0]->soleLiteral()?->value);
        self::assertSame('"a" < ?', $p->entry(new QueryState(), new ArrayEntry(Domain::literal(0), QueryState::list([Domain::literal('a'), Domain::literal('<'), Domain::literal(1)])), 'and')->items('where')[0]->soleLiteral()?->value);
        self::assertFalse($p->entry(new QueryState(), new ArrayEntry(null, Domain::literal('a')), 'and')->get('problem')->isExact());
        self::assertFalse($p->entry(new QueryState(), new ArrayEntry(null, Domain::of(new ArrayTerm([], false))), 'and')->get('problem')->isExact());
        self::assertFalse($p->entry(new QueryState(), new ArrayEntry(Domain::unknown(), Domain::literal(1)), 'and')->get('problem')->isExact());
    }
}
