<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Schema\Definition\ColumnPrimaryKey;
use SqlSemantics\Statement\Schema\Definition\ConflictAction;
use SqlSemantics\Statement\Schema\Definition\KeyDirection;

#[CoversClass(ColumnPrimaryKey::class)]
#[Small]
final class ColumnPrimaryKeyTest extends TestCase
{
    public function testToStringRetainsTheConstraintMeaning(): void
    {
        $constraint = new ColumnPrimaryKey(KeyDirection::Descending, ConflictAction::Abort, name: new Name('pk'));
        self::assertSame('CONSTRAINT pk PRIMARY KEY DESC ON CONFLICT ABORT', $constraint->toString());
    }
}
