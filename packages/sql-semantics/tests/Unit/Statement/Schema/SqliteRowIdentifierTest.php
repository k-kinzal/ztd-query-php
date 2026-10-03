<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Declaration\Builtin;
use SqlSemantics\Statement\Declaration\Nullability;
use SqlSemantics\Statement\Declaration\TypeDescriptor;
use SqlSemantics\Statement\Identifier\Comparison;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Schema\Column;
use SqlSemantics\Statement\Schema\SqliteRowIdentifier;

#[CoversClass(SqliteRowIdentifier::class)]
#[Small]
final class SqliteRowIdentifierTest extends TestCase
{
    #[TestWith(['rowid', true])]
    #[TestWith(['_ROWID_', true])]
    #[TestWith(['oid', true])]
    #[TestWith(['id', false])]
    public function testMatchesRecognizesOnlyTheImplicitNames(string $name, bool $expected): void
    {
        $identifier = new SqliteRowIdentifier();
        self::assertSame($expected, $identifier->matches($name, Comparison::AsciiInsensitive));
        self::assertSame(Builtin::Integer, $identifier->column->type->name);
        self::assertSame(Nullability::NotNull, $identifier->column->nullability);
    }

    public function testMatchesKeepsAnOriginalPrimaryKeyDeclarationAsItsIdentity(): void
    {
        $column = new Column(new Name('id'), new TypeDescriptor(Builtin::Integer), Nullability::NotNull);
        $identifier = new SqliteRowIdentifier($column);
        self::assertSame($column, $identifier->alias);
        self::assertSame($column, $identifier->column);
        self::assertTrue($identifier->matches('rowid', Comparison::AsciiInsensitive));
    }
}
