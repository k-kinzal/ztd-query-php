<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Lowerer\Declaration\Metadata;

use Deriver\Deriver\Lowerer\Declaration\Metadata\ClassConstant;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Deriver\Lowerer\Declaration\Metadata\ClassConstant
 */
#[CoversClass(ClassConstant::class)]
#[Small]
final class ClassConstantTest extends TestCase
{
    public function testInitializePreservesTheDeclaredContract(): void
    {
        $constant = new ClassConstant('Box', 'A', \Deriver\Deriver\Lowerer\Declaration\Metadata\Visibility::Private, 'int');
        self::assertSame('Box', $constant->className);
        self::assertSame(\Deriver\Deriver\Lowerer\Declaration\Metadata\Visibility::Private, $constant->visibility);
        self::assertSame('int', $constant->type);
    }
}
