<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\IR;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Internal\IR\ClassConstant
 */
#[CoversClass(\Deriver\Internal\IR\ClassConstant::class)]
#[Small]
final class ClassConstantTest extends TestCase
{
    public function testInitializePreservesTheDeclaredContract(): void
    {
        $constant = new \Deriver\Internal\IR\ClassConstant('Box', 'A', 'private', 'int');
        self::assertSame('Box', $constant->className);
        self::assertSame('private', $constant->visibility);
        self::assertSame('int', $constant->type);
    }
}
