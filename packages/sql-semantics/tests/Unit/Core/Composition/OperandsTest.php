<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Composition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\Composition\Operands;
use SqlSemantics\Statement\Model\Sqlite\Value\TermWithInteger_298801b2 as Integer;

#[CoversClass(Operands::class)]
#[UsesClass(\SqlSemantics\Statement\Assertion::class)]
#[UsesClass(\SqlSemantics\Statement\Comments::class)]
#[Small]
final class OperandsTest extends TestCase
{
    public function testFitsComparesTheOperandPowerWithThePositionMinimum(): void
    {
        $weak = new Integer('1');
        $strong = new Integer('2');
        $form = Integer::class;
        $operands = new Operands([$weak::class => ['v1' => 3]], [$form => ['v1' => [0 => 3, 2 => 4]]], [$weak::class => 'expr'], 'v1');
        self::assertTrue($operands->fits($weak, $form, 0, 'expr'));
        self::assertFalse($operands->fits($strong, $form, 2, 'expr'));
        self::assertTrue($operands->fits($strong, $form, 1, 'expr'));
    }

    public function testAValueWithoutAPowerBindsAsStronglyAsAnything(): void
    {
        $operands = new Operands([], [Integer::class => ['v1' => [1 => 99]]], [], 'v1');
        self::assertTrue($operands->fits(new Integer('1'), Integer::class, 1, 'expr'));
    }

    public function testAnOperandOfAnotherRuleIsNotCompared(): void
    {
        $value = new Integer('1');
        $operands = new Operands([$value::class => ['v1' => 1]], [Integer::class => ['v1' => [1 => 99]]], [$value::class => 'bool_pri'], 'v1');
        self::assertTrue($operands->fits($value, Integer::class, 1, 'expr'));
        self::assertFalse($operands->fits($value, Integer::class, 1, 'bool_pri'));
    }

    public function testAnotherReleaseHasItsOwnMinimums(): void
    {
        $value = new Integer('1');
        $operands = new Operands([$value::class => ['v2' => 1]], [Integer::class => ['v1' => [1 => 99], 'v2' => [1 => 1]]], [$value::class => 'expr'], 'v2');
        self::assertTrue($operands->fits($value, Integer::class, 1, 'expr'));
    }
}
