<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Builtin;

use Deriver\Model\Builtin\ArrayMutation;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ArrayMutation::class)]
#[UsesClass(\Deriver\Value\Arrays::class)]
#[UsesClass(\Deriver\Value\Operations::class)]
#[UsesClass(Term::class)]
#[Small]
final class ArrayMutationTest extends TestCase
{
    /**
     * @param list<Term> $values Bound arguments
     * @param array<string, mixed> $expected Updated array and return value
     */
    #[DataProvider('providerClosedMutations')]
    public function testApplyFollowsPhpKeyRenumberingOnClosedArrays(string $name, array $values, array $expected): void
    {
        self::assertSame($expected, (new ArrayMutation())->apply($name, $values)->native());
    }

    /**
     * @return iterable<string, array{string, list<Term>, array<string, mixed>}>
     */
    public static function providerClosedMutations(): iterable
    {
        $mixed = Term::fromNative([5 => 'a', 'k' => 'b', 9 => 'c']);
        yield 'shift renumbers integer keys' => ['array_shift', [$mixed], ['array' => ['k' => 'b', 0 => 'c'], 'result' => 'a']];
        yield 'pop keeps keys' => ['array_pop', [$mixed], ['array' => [5 => 'a', 'k' => 'b'], 'result' => 'c']];
        yield 'push appends after the largest key' => ['array_push', [$mixed, Term::fromNative(['x', 'y'])], ['array' => [5 => 'a', 'k' => 'b', 9 => 'c', 10 => 'x', 11 => 'y'], 'result' => 5, 'appended' => true, 'partial' => [5 => 'a', 'k' => 'b', 9 => 'c', 10 => 'x', 11 => 'y']]];
        yield 'unshift renumbers integer keys' => ['array_unshift', [$mixed, Term::fromNative(['x'])], ['array' => ['x', 'a', 'k' => 'b', 'c'], 'result' => 4]];
        yield 'unshift without values renumbers' => ['array_unshift', [$mixed, Term::array([])], ['array' => ['a', 'k' => 'b', 'c'], 'result' => 3]];
        yield 'shift of an empty array' => ['array_shift', [Term::array([])], ['array' => [], 'result' => null]];
    }

    public function testRemoveTracksTheNextIndexLikeArrayPop(): void
    {
        $mutation = new ArrayMutation();
        self::assertSame(2, $mutation->remove(Term::fromNative([1, 2, 3]), false)->operands['array']->attributes['next'] ?? null);
        self::assertSame(4, $mutation->remove(Term::fromNative([3 => 'a', 1 => 'b']), false)->operands['array']->attributes['next'] ?? null);
        self::assertSame(5, $mutation->remove(Term::fromNative([5 => 'a']), false)->operands['array']->attributes['next'] ?? null);
        self::assertSame(PHP_INT_MAX, $mutation->remove(Term::fromNative([PHP_INT_MAX => 'a']), false)->operands['array']->attributes['next'] ?? null);
        self::assertArrayNotHasKey('next', $mutation->remove(new Term('array', operands: [7 => Term::constant(1)], attributes: ['open' => false, 'next' => 9]), true)->operands['array']->attributes);
    }

    public function testPushKeepsValuesInsertedBeforeTheOccupiedMaximumIndex(): void
    {
        $record = (new ArrayMutation())->push(Term::fromNative([PHP_INT_MAX - 1 => 'a']), [Term::constant('b'), Term::constant('c')]);
        self::assertFalse($record->operands['appended']->literal);
        self::assertSame([PHP_INT_MAX - 1 => 'a', PHP_INT_MAX => 'b'], $record->operands['partial']->native());
    }

    public function testRemoveKeepsSymbolicArraysAsCorrelatedCalls(): void
    {
        $array = Term::parameter('c', 'array');
        $record = (new ArrayMutation())->remove($array, true);
        self::assertSame(['intrinsic', 'array_shift', [$array], 'mixed'], [$record->operands['result']->kind, $record->operands['result']->literal, $record->operands['result']->operands, $record->operands['result']->attributes['type']]);
        self::assertSame(['array_shift:array', 'array'], [$record->operands['array']->literal, $record->operands['array']->attributes['type']]);
        self::assertSame('array_pop', (new ArrayMutation())->remove(Term::array([Term::constant(1)], true), false)->operands['result']->literal);
    }

    public function testPushOnSymbolicArraysKeepsAnOccupiedIndexFailure(): void
    {
        $array = Term::parameter('c', 'array');
        $record = (new ArrayMutation())->push($array, [Term::constant('x'), Term::constant('y')]);
        self::assertSame('array-merge', $record->operands['array']->kind);
        self::assertSame(['intrinsic', 'count'], [$record->operands['result']->kind, $record->operands['result']->literal]);
        self::assertSame('array_push:appendable', $record->operands['appended']->literal);
        self::assertSame('array_push:partial', $record->operands['partial']->literal);
        self::assertSame($array, (new ArrayMutation())->push($array, [Term::constant('x')])->operands['partial']);
        self::assertTrue((new ArrayMutation())->push($array, [])->operands['appended']->literal);
    }

    public function testPushOnRenumberedMergesCannotReachTheMaximumIndex(): void
    {
        $merged = new Term('array-merge', operands: [Term::array([]), Term::parameter('c', 'array')], attributes: ['type' => 'array']);
        self::assertTrue((new ArrayMutation())->push($merged, [Term::constant('x')])->operands['appended']->literal);
    }

    public function testPrependOnSymbolicArraysKeepsTheKnownHead(): void
    {
        $record = (new ArrayMutation())->prepend(Term::parameter('c', 'array'), [Term::constant('id')]);
        self::assertSame('array-merge', $record->operands['array']->kind);
        self::assertSame(['id'], $record->operands['array']->operands[0]->native());
    }

    public function testApplyRejectsNamedAndUnknownVariadicValues(): void
    {
        $mutation = new ArrayMutation();
        self::assertSame('ArgumentCountError', $mutation->apply('array_push', [Term::array([]), Term::fromNative(['x' => 1])])->literal);
        self::assertSame('ArgumentCountError', $mutation->apply('array_unshift', [Term::array([]), Term::fromNative(['x' => 1])])->literal);
        self::assertSame('UNSUPPORTED_MODEL_CASE', $mutation->apply('array_push', [Term::array([]), Term::parameter('values', 'array')])->literal);
    }

    public function testClosedAcceptsOnlyArraysWithoutUnknownEntries(): void
    {
        $mutation = new ArrayMutation();
        self::assertTrue($mutation->closed(Term::array([])));
        self::assertFalse($mutation->closed(Term::array([], true)));
        self::assertFalse($mutation->closed(Term::parameter('c', 'array')));
    }

    public function testCountMatchesTheBoundCountCall(): void
    {
        $array = Term::parameter('c', 'array');
        $count = (new ArrayMutation())->count($array);
        self::assertSame([$array, 0], [$count->operands[0], $count->operands[1]->literal]);
        self::assertSame('int', $count->attributes['type']);
    }

    public function testRecordAddsAppendOutcomesOnlyWhenBothAreKnown(): void
    {
        $mutation = new ArrayMutation();
        self::assertSame(['array', 'result'], array_keys($mutation->record(Term::array([]), Term::constant(0))->operands));
        self::assertSame(['array', 'result', 'appended', 'partial'], array_keys($mutation->record(Term::array([]), Term::constant(0), Term::constant(true), Term::array([]))->operands));
    }
}
