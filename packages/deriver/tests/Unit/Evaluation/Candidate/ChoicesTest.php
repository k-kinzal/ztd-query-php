<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Candidate;

use Deriver\Evaluation\Candidate\Choices;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class ChoicesTest extends TestCase
{
    public function testMakeRetainsDifferentSymbolicExpressions(): void
    {
        $choice = (new Choices())->make([[new Term('binary', '+', [Term::constant(5),Term::parameter('x')]),[]],[new Term('binary', '+', [Term::constant(3),Term::parameter('y')]),[]]]);
        self::assertCount(2, $choice->operands);
    }
    public function testAlternativesRetainsGuardIdentity(): void
    {
        $choices = new Choices();
        $value = $choices->make([[Term::constant(1),['branch' => true]],[Term::constant(2),['branch' => false]]]);
        self::assertSame(['branch' => false], $choices->alternatives($value)[1][1]);
    }
    public function testMergeRejectsContradictoryBranches(): void
    {
        self::assertNull((new Choices())->merge(['branch' => true], ['branch' => false]));
    }
    public function testApplyRetainsUnenumeratedProducts(): void
    {
        $choices = new Choices();
        $operand = $choices->make([[Term::constant(1),[]],[Term::constant(2),[]]]);
        $result = $choices->apply('+', [$operand,$operand], static fn (array $terms): Term => (new \Deriver\Value\Operations())->binary('+', $terms[0], $terms[1]), 1);
        self::assertSame('ENUMERATION_LIMIT', $result->attributes['reason']);
        self::assertSame([$operand,$operand], $result->operands);
    }
}
