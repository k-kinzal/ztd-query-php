<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Privilege;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Privilege\DatabaseScope;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(DatabaseScope::class)]
#[Medium]
final class DatabaseScopeTest extends TestCase
{
    public function testKeepsTheName(): void
    {
        self::assertSame('app', (new DatabaseScope('app'))->database);
    }

    public function testRejectsAnEmptyName(): void
    {
        $this->expectException(InvalidStructure::class);
        new DatabaseScope('');
    }
}
