<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Database;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Database\DatabaseCollation;
use SqlSemantics\Model\Definition\Database\ServerCharacterInheritance;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(DatabaseCollation::class)]
#[Medium]
final class DatabaseCollationTest extends TestCase
{
    public function testNamedDefaultPreservesIdentifierCase(): void
    {
        $option = new DatabaseCollation('CaseSensitive');
        self::assertSame('CaseSensitive', $option->name);
        self::assertSame(ServerCharacterInheritance::Inherit, (new DatabaseCollation(ServerCharacterInheritance::Inherit))->name);
    }

    public function testEmptyNameCannotFormADefault(): void
    {
        $this->expectException(InvalidStructure::class);
        new DatabaseCollation('');
    }
}
