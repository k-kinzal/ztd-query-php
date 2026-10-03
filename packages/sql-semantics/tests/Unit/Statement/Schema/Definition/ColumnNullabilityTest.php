<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Schema\Definition\ColumnNullability;
use SqlSemantics\Statement\Schema\Definition\ConflictAction;

#[CoversClass(ColumnNullability::class)]
#[Small]
final class ColumnNullabilityTest extends TestCase
{
    public function testToStringRetainsTheConstraintMeaning(): void
    {
        $constraint = new ColumnNullability(false, ConflictAction::Fail, new Name('required'));
        self::assertSame('CONSTRAINT required NOT NULL ON CONFLICT FAIL', $constraint->toString());
    }
}
