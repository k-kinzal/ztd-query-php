<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\TypeSystem\Domain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\TypeSystem\Domain\DomainNotNull;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(DomainNotNull::class)]
final class DomainNotNullTest extends TestCase
{
    public function testRetainsTheOptionalName(): void
    {
        self::assertNull((new DomainNotNull())->name);
        self::assertSame('present', (new DomainNotNull('present'))->name);
    }

    public function testRejectsAnEmptyName(): void
    {
        $this->expectException(InvalidStructure::class);
        new DomainNotNull('');
    }
}
