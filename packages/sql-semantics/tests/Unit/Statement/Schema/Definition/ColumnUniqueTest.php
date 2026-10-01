<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Schema\Definition\ColumnUnique;
use SqlSemantics\Statement\Schema\Definition\ConflictAction;

#[CoversClass(ColumnUnique::class)]
#[Small]
final class ColumnUniqueTest extends TestCase
{
    public function testToStringRetainsTheConstraintMeaning(): void
    {
        $constraint = new ColumnUnique(ConflictAction::Ignore, new Name('uq'));
        self::assertSame('CONSTRAINT uq UNIQUE ON CONFLICT IGNORE', $constraint->toString());
    }
}
