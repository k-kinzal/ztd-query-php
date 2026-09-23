<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Database;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Database\DatabaseCharacterSet;
use SqlSemantics\Model\Definition\Database\ServerCharacterInheritance;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(DatabaseCharacterSet::class)]
#[Medium]
final class DatabaseCharacterSetTest extends TestCase
{
    public function testNamedDefaultPreservesIdentifierCase(): void
    {
        $option = new DatabaseCharacterSet('CaseSensitive');
        self::assertSame('CaseSensitive', $option->name);
        self::assertSame(ServerCharacterInheritance::Inherit, (new DatabaseCharacterSet(ServerCharacterInheritance::Inherit))->name);
    }

    public function testEmptyNameCannotFormADefault(): void
    {
        $this->expectException(InvalidStructure::class);
        new DatabaseCharacterSet('');
    }
}
