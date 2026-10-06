<?php

declare(strict_types=1);

namespace Tests\Semantic;

use Deriver\Query\Budget;
use Deriver\Query\QueryScope;
use Deriver\Query\ValueQuery;
use Deriver\Result\Alternative;
use Deriver\Result\Exceptional;
use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use Tests\Fake\Analysis;

/**
 * By-reference array mutations keep every PHP outcome of arrays whose contents are unknown.
 */
#[CoversNothing]
#[Medium]
final class ArrayMutationTest extends TestCase
{
    /**
     * PHP's array_shift() returns null and leaves an empty array unchanged, and otherwise removes and returns its first element.
     * @throws JsonException If captured fixture values cannot be encoded
     */
    public function testShiftOfAnUnknownArrayKeepsTheEmptyAndNonEmptyOutcomes(): void
    {
        $result = Analysis::returns('<?php function target(array $c){$first=array_shift($c);return [$first,$c];}');
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
        $returns = array_map(static fn (Alternative $outcome): Term => $outcome->values['return'], $result->normalOutcomes);
        self::assertCount(2, $returns);
        $empty = array_values(array_filter($returns, static fn (Term $value): bool => $value->operands[0]->kind === 'constant'))[0];
        self::assertNull($empty->operands[0]->literal);
        self::assertSame(['parameter', 'c'], [$empty->operands[1]->kind, $empty->operands[1]->literal]);
        $removed = array_values(array_filter($returns, static fn (Term $value): bool => $value->operands[0]->kind === 'intrinsic'))[0];
        self::assertSame(['array_shift', 'array_shift:array'], [$removed->operands[0]->literal, $removed->operands[1]->literal]);
        self::assertSame(['array', 'array'], [$removed->operands[1]->attributes['type'], $removed->operands[1]->operands[0]->attributes['type']]);
    }

    /**
     * After a non-empty check, PHP's array_shift() and array_pop() cannot return null for an empty array.
     * @throws JsonException If captured fixture values cannot be encoded
     */
    public function testRemovalAfterAnEmptinessCheckHasNoEmptyOutcome(): void
    {
        foreach (['array_shift', 'array_pop'] as $function) {
            $result = Analysis::returns('<?php function target(array $c){if(!$c){return "empty";}return ' . $function . '($c);}');
            self::assertSame([], $result->frontiers);
            self::assertEqualsCanonicalizing([['constant', 'empty'], ['intrinsic', $function]], array_map(static fn (Term $value): array => [$value->kind, $value->literal], array_map(static fn (Alternative $outcome): Term => $outcome->values['return'], $result->normalOutcomes)));
        }
    }

    /**
     * PHP's array_push() returns the new element count, and throws Error when the next integer key of the array is already occupied.
     * @throws JsonException If captured fixture values cannot be encoded
     */
    public function testPushOntoAnUnknownArrayCountsTheResultAndKeepsTheOccupiedIndexError(): void
    {
        $result = Analysis::returns('<?php function target(array $c){$n=array_push($c,"x");return [$n,count($c),$c];}');
        self::assertSame([], $result->frontiers);
        self::assertSame(['Error'], array_map(static fn (Exceptional $outcome) => $outcome->exception->literal, $result->exceptionalOutcomes));
        [$returned] = array_map(static fn (Alternative $outcome): Term => $outcome->values['return'], $result->normalOutcomes);
        self::assertEquals($returned->operands[0], $returned->operands[1]);
        self::assertSame('array-merge', $returned->operands[2]->kind);
        self::assertSame(['x'], $returned->operands[2]->operands[1]->native());
    }

    /**
     * Unpacking renumbers integer keys from zero, so a following append cannot reach the maximum integer key.
     * @throws JsonException If captured fixture values cannot be encoded
     */
    public function testPushOntoAnUnpackedArrayCannotFail(): void
    {
        $result = Analysis::returns('<?php function target(array $c){$a=[...$c];array_push($a,"x");return $a;}');
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertCount(1, $result->normalOutcomes);
    }

    /**
     * PHP's array_unshift() places the given values first, at keys 0, 1, and so on.
     * @throws JsonException If captured fixture values cannot be encoded
     */
    public function testUnshiftOntoAnUnknownArrayKnowsTheLeadingValues(): void
    {
        $result = Analysis::returns('<?php function target(array $c){array_unshift($c,"id","name");return [$c[0],$c[1],in_array("name",$c,true)];}');
        self::assertSame([], $result->frontiers);
        self::assertSame([], $result->exceptionalOutcomes);
        self::assertSame([['id', 'name', true]], array_map(static fn (Term $value) => $value->native(), array_map(static fn (Alternative $outcome): Term => $outcome->values['return'], $result->normalOutcomes)));
    }

    /**
     * A recursive condition builder that shifts keys from its argument no longer stops at an unmodeled call.
     * @throws JsonException If captured fixture values cannot be encoded
     */
    public function testRecursiveConditionBuilderKeepsTheEmptyCondition(): void
    {
        $session = Analysis::session('<?php function q($s){} function where(array $c): string { if (!$c) return "1=1"; $k = array_shift($c); return "$k = ? AND " . where($c); } function target(array $c){ q("SELECT * FROM t WHERE " . where($c)); }');
        $result = $session->derive(new ValueQuery($session->callsTo('q')[0]->argument(0), scope: QueryScope::fromEntrypoints([new \Deriver\Project\EntryPoint('target', [Term::parameter('c', 'array')])]), budget: new Budget(recursion: 2)));
        self::assertNotContains('MISSING_CALL_MODEL', array_column($result->frontiers, 'code'));
        self::assertContains('SELECT * FROM t WHERE 1=1', array_map(static fn (Alternative $outcome) => $outcome->values['value']->isConcrete() ? $outcome->values['value']->native() : null, $result->normalOutcomes));
    }

    /**
     * PHP's array_key_first() and array_key_last() return the boundary keys in insertion order.
     * @throws JsonException If captured fixture values cannot be encoded
     */
    public function testBoundaryKeysOfAConcreteArray(): void
    {
        $result = Analysis::returns('<?php function target(){$c=["a"=>1,"b"=>2];return [array_key_first($c),array_key_last($c)];}');
        self::assertSame([], $result->frontiers);
        self::assertSame([['a', 'b']], array_map(static fn (Term $value) => $value->native(), array_map(static fn (Alternative $outcome): Term => $outcome->values['return'], $result->normalOutcomes)));
    }
}
