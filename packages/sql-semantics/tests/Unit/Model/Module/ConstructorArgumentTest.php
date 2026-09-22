<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Module;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Module\ConstructorArgument;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(ConstructorArgument::class)]
final class ConstructorArgumentTest extends TestCase
{
    public function testPreservesTheModuleInputText(): void
    {
        $argument = new ConstructorArgument('tokenize = "porter ascii"');
        self::assertSame('tokenize = "porter ascii"', $argument->text);
    }

    public function testRejectsEscapingTheArgumentBoundary(): void
    {
        $this->expectException(InvalidStructure::class);
        new ConstructorArgument('title); SELECT 1; --');
    }

    public function testRejectsTwoTopLevelArguments(): void
    {
        $this->expectException(InvalidStructure::class);
        new ConstructorArgument('title, body');
    }
}
