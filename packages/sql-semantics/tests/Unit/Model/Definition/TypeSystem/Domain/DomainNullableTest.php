<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\TypeSystem\Domain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\TypeSystem\Domain\DomainNullable;
use SqlSemantics\Model\Validation\InvalidStructure;

#[CoversClass(DomainNullable::class)]
final class DomainNullableTest extends TestCase
{
    public function testRetainsTheOptionalName(): void
    {
        self::assertNull((new DomainNullable())->name);
        self::assertSame('open', (new DomainNullable('open'))->name);
    }

    public function testRejectsAnEmptyName(): void
    {
        $this->expectException(InvalidStructure::class);
        new DomainNullable('');
    }
}
