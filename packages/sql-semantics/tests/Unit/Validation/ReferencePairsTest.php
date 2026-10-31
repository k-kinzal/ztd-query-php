<?php

declare(strict_types=1);

namespace Tests\Unit\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Validation\ReferencePairs;

#[CoversClass(ReferencePairs::class)]
#[Small]
final class ReferencePairsTest extends TestCase
{
    public function testAcceptKeepsReferencesOneToOneDespiteEqualValues(): void
    {
        $left = new Name('a');
        $right = new Name('a');
        $differentLeft = new Name('a');
        $differentRight = new Name('a');
        $pairs = new ReferencePairs();

        self::assertTrue($pairs->accept($left, $right));
        self::assertTrue($pairs->accept($left, $right));
        self::assertFalse($pairs->accept($left, $differentRight));
        self::assertFalse($pairs->accept($differentLeft, $right));
        self::assertTrue($pairs->accept($differentLeft, $differentRight));
    }

    public function testSeenDoesNotConflateEqualLookingObjects(): void
    {
        $left = new Name('a');
        $right = new Name('a');
        $different = new Name('a');
        $pairs = new ReferencePairs();

        self::assertFalse($pairs->seen($left, $right));
        self::assertFalse($pairs->seen($left, $different));
        self::assertTrue($pairs->seen($left, $right));
    }
}
