<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Enumeration;

use Deriver\Deriver\Evaluator\Enumeration\Choices;
use Deriver\Deriver\Evaluator\Term;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class ChoicesTest extends TestCase
{
    public function testMapVisitsSharedValuesOnceAndRetainsGuards(): void
    {
        $choices = new Choices();
        $leaf = Term::constant(4);
        $value = $choices->make([[$leaf, ['branch' => true]], [$leaf, ['branch' => false]]]);
        $calls = 0;
        $mapped = $choices->map($value, static function (Term $value) use (&$calls): Term {
            $calls++;
            return new Term($value->kind, $value->literal, attributes: ['mapped' => true]);
        });

        self::assertSame(1, $calls);
        self::assertSame($mapped->operands[0]->operands[0], $mapped->operands[1]->operands[0]);
        self::assertSame(['guard:branch' => true], $mapped->operands[0]->attributes);
        self::assertSame(['guard:branch' => false], $mapped->operands[1]->attributes);
        self::assertSame(['mapped' => true], $mapped->operands[0]->operands[0]->attributes);
        self::assertSame([], $leaf->attributes);
    }

    public function testMakeRetainsDifferentSymbolicExpressions(): void
    {
        $choice = (new Choices())->make([[new Term(\Deriver\Deriver\Evaluator\TermKind::Binary, '+', [Term::constant(5),Term::parameter('x')]),[]],[new Term(\Deriver\Deriver\Evaluator\TermKind::Binary, '+', [Term::constant(3),Term::parameter('y')]),[]]]);
        self::assertCount(2, $choice->operands);
    }
    public function testAlternativesRetainsGuardIdentity(): void
    {
        $choices = new Choices();
        $value = $choices->make([[Term::constant(1),['branch' => true]],[Term::constant(2),['branch' => false]]]);
        self::assertSame(['branch' => false], iterator_to_array($choices->alternatives($value), false)[1][1]);
    }
    public function testMergeRejectsContradictoryBranches(): void
    {
        self::assertNull((new Choices())->merge(['branch' => true], ['branch' => false]));
    }
    public function testApplyRetainsUnenumeratedProducts(): void
    {
        $choices = new Choices();
        $operand = $choices->make([[Term::constant(1),[]],[Term::constant(2),[]]]);
        $result = $choices->apply('+', [$operand,$operand], static fn (array $terms): Term => (new \Deriver\Deriver\Evaluator\Scalar\Operations())->binary('+', $terms[0], $terms[1]), 1);
        self::assertSame('ENUMERATION_LIMIT', $result->attributes['reason']);
        self::assertSame([$operand,$operand], $result->operands);
    }

    public function testApplySharesWorkBetweenCompatibleAlternatives(): void
    {
        $search = new \Deriver\Deriver\Evaluator\Search\Search();
        $choices = new Choices(search: $search);
        $operand = $choices->make([[Term::constant(1), ['branch' => true]], [Term::constant(2), ['branch' => false]]]);
        $visited = [];
        $result = $choices->apply('call', [$operand], static function (array $values) use ($search, &$visited): Term {
            $visited[] = $values[0]->literal;
            $search->checkpoint();
            $visited[] = $values[0]->literal;
            return $values[0];
        }, 10);
        self::assertSame([1, 2, 1, 2], $visited);
        self::assertSame(['branch' => false], iterator_to_array($choices->alternatives($result), false)[1][1]);
    }
}
