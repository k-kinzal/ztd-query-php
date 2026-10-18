<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Lowerer\Declaration\Traits;

use Deriver\Deriver\Lowerer\Declaration\Traits\LexicalConstants;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Deriver\Lowerer\Declaration\Traits\LexicalConstants
 */
#[CoversClass(LexicalConstants::class)]
#[Small]
final class LexicalConstantsTest extends TestCase
{
    public function testEnterNodeTracksTheOriginalMethodBeforeAliasBinding(): void
    {
        $visitor = new LexicalConstants('T');
        $visitor->enterNode(new \PhpParser\Node\Stmt\ClassMethod('method'));
        self::assertSame(['method'], $visitor->functions);
    }
    public function testLeaveNodeDistinguishesTraitIdentityFromTheConsumingClass(): void
    {
        $visitor = new LexicalConstants('T');
        $method = new \PhpParser\Node\Stmt\ClassMethod('f');
        $visitor->enterNode($method);
        $constant = $visitor->leaveNode(new \PhpParser\Node\Scalar\MagicConst\Method());
        self::assertInstanceOf(\PhpParser\Node\Scalar\String_::class, $constant);
        self::assertSame('T::f', $constant->value);
        self::assertNull($visitor->leaveNode(new \PhpParser\Node\Scalar\MagicConst\Class_()));
        $visitor->leaveNode($method);
        self::assertSame([], $visitor->functions);
    }
}
