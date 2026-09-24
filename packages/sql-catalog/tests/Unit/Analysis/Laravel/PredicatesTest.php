<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis\Laravel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Analysis\Laravel\Grammar;
use SqlCatalog\Analysis\Laravel\Predicates;
use SqlCatalog\Analysis\Laravel\QueryState;
use SqlCatalog\Evaluation\ArrayEntry;
use SqlCatalog\Evaluation\ArrayTerm;
use SqlCatalog\Evaluation\Domain;
use SqlCatalog\Evaluation\LiteralTerm;
use SqlCatalog\Evaluation\ObjectTerm;
use SqlCatalog\Evaluation\OpaqueTerm;
use SqlCatalog\Evaluation\PatternTerm;
use SqlCatalog\Text\LiteralText;
use SqlCatalog\Text\TextGeneralization;
use SqlCatalog\Text\TextHole;
use SqlCatalog\Text\TextPattern;
use SqlCatalog\Type\TypeShape;

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
        $predicates = new Predicates(new Grammar('sqlite'));
        $state = $predicates->basic(new QueryState(), [Domain::literal('id'), Domain::literal(1)], 'and');
        $state = $predicates->apply($state, 'orwhere', [Domain::literal('id'), Domain::literal(2)]);
        self::assertSame('or "id" = ?', $state?->items('where')[1]->soleLiteral()?->value);
        self::assertNull($predicates->apply(new QueryState(), 'unknown', []));
    }

    public function testAddDoesNotPrefixTheFirstPredicateWithABoolean(): void
    {
        $p = new Predicates(new Grammar('sqlite'));
        $state = $p->add(new QueryState(), Domain::literal('a = ?'), [Domain::literal(1)], 'or');
        $state = $p->add($state, Domain::literal('b = ?'), [Domain::literal(2)]);
        self::assertSame(['a = ?', 'and b = ?'], array_map(static fn (Domain $v): mixed => $v->soleLiteral()?->value, $state->items('where')));
        self::assertCount(2, $state->items('whereBindings'));
    }

    public function testBasicNormalizesNullAndPreservesComparisonBindings(): void
    {
        $p = new Predicates(new Grammar('sqlite'));
        $state = $p->basic(new QueryState(), [Domain::literal('id'), Domain::literal('>'), Domain::literal(2)], 'and');
        self::assertSame('"id" > ?', $state->items('where')[0]->soleLiteral()?->value);
        self::assertSame(2, $state->items('whereBindings')[0]->soleLiteral()?->value);
        $null = $p->basic(new QueryState(), [Domain::literal('id'), Domain::literal('!='), Domain::literal(null)], 'and');
        self::assertSame('"id" is not null', $null->items('where')[0]->soleLiteral()?->value);
        self::assertSame([], $null->items('whereBindings'));
        self::assertFalse($p->basic(new QueryState(), [Domain::literal('id'), Domain::unknown()], 'and')->get('problem')->isExact());
        self::assertFalse($p->basic(new QueryState(), [], 'and')->get('problem')->isExact());
        self::assertFalse($p->basic(new QueryState(), [Domain::literal('id'), Domain::literal('bad'), Domain::literal(1)], 'and')->get('problem')->isExact());
    }

    public function testNullsSupportsMultipleColumnsWithoutBindings(): void
    {
        $p = new Predicates(new Grammar('sqlite'));
        $state = $p->nulls(new QueryState(), [QueryState::list([Domain::literal('a'), Domain::literal('b')])], 'or', false);
        self::assertSame('"a" is null', $state->items('where')[0]->soleLiteral()?->value);
        self::assertSame('or "b" is null', $state->items('where')[1]->soleLiteral()?->value);
        self::assertFalse($p->nulls(new QueryState(), [], 'and', false)->get('problem')->isExact());
    }

    public function testInHandlesEmptySetsAndOrderedValues(): void
    {
        $p = new Predicates(new Grammar('sqlite'));
        $empty = [Domain::literal('id'), QueryState::list([])];
        self::assertSame('0 = 1', $p->in(new QueryState(), $empty, 'and', false)->items('where')[0]->soleLiteral()?->value);
        self::assertSame('1 = 1', $p->in(new QueryState(), $empty, 'and', true)->items('where')[0]->soleLiteral()?->value);
        $state = $p->in(new QueryState(), [Domain::literal('id'), QueryState::list([Domain::literal(2), Domain::literal(1)])], 'and', true);
        self::assertSame('"id" not in (?, ?)', $state->items('where')[0]->soleLiteral()?->value);
        self::assertSame(2, $state->items('whereBindings')[0]->soleLiteral()?->value);
        self::assertFalse($p->in(new QueryState(), [Domain::literal('id'), Domain::unknown()], 'and', false)->get('problem')->isExact());
    }

    public function testBetweenKeepsBothBoundsInOrder(): void
    {
        $p = new Predicates(new Grammar('mysql'));
        $state = $p->between(new QueryState(), [Domain::literal('id'), QueryState::list([Domain::literal(2), Domain::literal(9)])], 'and', true);
        self::assertSame('`id` not between ? and ?', $state->items('where')[0]->soleLiteral()?->value);
        self::assertSame([2, 9], array_map(static fn (Domain $v): mixed => $v->soleLiteral()?->value, $state->items('whereBindings')));
        self::assertFalse($p->between(new QueryState(), [], 'and', false)->get('problem')->isExact());
    }

    public function testColumnDoesNotTurnTheRightIdentifierIntoABinding(): void
    {
        $p = new Predicates(new Grammar('sqlite'));
        $state = $p->column(new QueryState(), [Domain::literal('a'), Domain::literal('<'), Domain::literal('b')], 'and');
        self::assertSame('"a" < "b"', $state->items('where')[0]->soleLiteral()?->value);
        self::assertSame([], $state->items('whereBindings'));
        self::assertFalse($p->column(new QueryState(), [], 'and')->get('problem')->isExact());
        self::assertFalse($p->column(new QueryState(), [Domain::literal('a'), Domain::unknown(), Domain::literal('b')], 'and')->get('problem')->isExact());
    }

    public function testRawKeepsBindingsAndLeavesIncompleteArraysOpen(): void
    {
        $p = new Predicates(new Grammar('sqlite'));
        $state = $p->raw(new QueryState(), [Domain::literal('id > ?'), QueryState::list([Domain::literal(2)])], 'and');
        self::assertSame('id > ?', $state->items('where')[0]->soleLiteral()?->value);
        self::assertSame(2, $state->items('whereBindings')[0]->soleLiteral()?->value);
        self::assertFalse($p->raw(new QueryState(), [Domain::literal('x'), Domain::of(new ArrayTerm([], false))], 'and')->get('problem')->isExact());
    }

    public function testInPreservesValuesFromAssociativeArraysAndRejectsNestedArrays(): void
    {
        $p = new Predicates(new Grammar('sqlite'));
        $values = Domain::of(new ArrayTerm([new ArrayEntry(Domain::literal('key'), Domain::literal(7))]));
        $state = $p->in(new QueryState(), [Domain::literal('id'), $values], 'and', false);
        self::assertSame('"id" in (?)', $state->items('where')[0]->soleLiteral()?->value);
        self::assertSame(7, $state->items('whereBindings')[0]->soleLiteral()?->value);
        self::assertFalse($p->in(new QueryState(), [Domain::literal('id'), QueryState::list([$values])], 'and', false)->get('problem')->isExact());
    }

    public function testNullsDoesNotDropUnknownColumnsOrAssociativeValues(): void
    {
        $p = new Predicates(new Grammar('sqlite'));
        self::assertFalse($p->nulls(new QueryState(), [Domain::of(new ArrayTerm([], false))], 'and', false)->get('problem')->isExact());
        $state = $p->nulls(new QueryState(), [Domain::of(new ArrayTerm([new ArrayEntry(Domain::literal('key'), Domain::literal('id'))]))], 'and', false);
        self::assertSame('"id" is null', $state->items('where')[0]->soleLiteral()?->value);
    }

    public function testGroupDisjunctionPreservesConjunctionsWithoutAddingParentheses(): void
    {
        $state = new QueryState(['where' => QueryState::list([Domain::literal('a = ?'), Domain::literal('and b = ?')])]);
        self::assertSame($state, (new Predicates(new Grammar('sqlite')))->groupDisjunction($state));
        $disjunction = $state->with('where', QueryState::list([Domain::literal('a = ?'), Domain::literal('or b = ?')]));
        self::assertSame('(a = ? or b = ?)', (new Predicates(new Grammar('sqlite')))->groupDisjunction($disjunction)->items('where')[0]->soleLiteral()?->value);
    }

    /**
     * @param list<Domain> $arguments
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerPredicateDispatch')]
    public function testApplyPreservesThePredicateAndBoolean(string $method, array $arguments, string $expected, int $bindings): void
    {
        $state = new QueryState(['where' => QueryState::list([Domain::literal('active = 1')])]);
        $after = (new Predicates(new Grammar('sqlite')))->apply($state, $method, $arguments);
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
}
