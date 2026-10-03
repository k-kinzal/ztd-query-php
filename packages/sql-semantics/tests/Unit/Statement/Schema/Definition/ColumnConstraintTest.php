<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Schema\Definition\ColumnConstraint;
use SqlSemantics\Statement\Schema\Definition\ColumnUnique;

#[CoversClass(ColumnConstraint::class)]
#[Small]
final class ColumnConstraintTest extends TestCase
{
    public function testToStringRetainsTheConstraintMeaning(): void
    {
        $constraint = new ColumnUnique();
        self::assertSame('UNIQUE', $constraint->toString());
    }
}
