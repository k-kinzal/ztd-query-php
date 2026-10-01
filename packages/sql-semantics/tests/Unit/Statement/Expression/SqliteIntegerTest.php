<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression;

use PDO;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Declaration\Builtin;
use SqlSemantics\Statement\Declaration\Nullability;
use SqlSemantics\Statement\Declaration\TypeDescriptor;
use SqlSemantics\Statement\Expression\SqliteInteger;
use SqlSemantics\Statement\Literal\Radix;
use SqlSemantics\Statement\Literal\UnsignedInteger;
use SqlSemantics\Statement\Type\Invalid;

#[CoversClass(SqliteInteger::class)]
#[Small]
final class SqliteIntegerTest extends TestCase
{
    #[TestWith(['9223372036854775807', Radix::Decimal, false, '9223372036854775807', Builtin::Integer, 'integer'])]
    #[TestWith(['9223372036854775808', Radix::Decimal, false, '9223372036854775808', Builtin::DoublePrecision, 'real'])]
    #[TestWith(['9223372036854775808', Radix::Decimal, true, '-9223372036854775808', Builtin::Integer, 'integer'])]
    #[TestWith(['ffffffffffffffff', Radix::Hexadecimal, false, '-1', Builtin::Integer, 'integer'])]
    #[TestWith(['8000000000000000', Radix::Hexadecimal, false, '-9223372036854775808', Builtin::Integer, 'integer'])]
    public function testTypeAndExactValueMatchTheDatabaseLiteralRules(string $digits, Radix $radix, bool $negative, string $value, Builtin $builtin, string $storageClass): void
    {
        $db = new PDO('sqlite::memory:');
        $literal = new SqliteInteger(new UnsignedInteger($digits, $radix), $negative);
        $result = $db->query('SELECT typeof(' . $literal->toString() . ')');
        self::assertInstanceOf(PDOStatement::class, $result);
        self::assertSame($storageClass, $result->fetchColumn());
        self::assertSame($value, $literal->value->value());
        $type = $literal->type();
        self::assertInstanceOf(TypeDescriptor::class, $type);
        self::assertSame($builtin, $type->name);
    }

    public function testTypeRepresentsAnInvalidWidthWithoutDiscardingTheLiteral(): void
    {
        $literal = new SqliteInteger(new UnsignedInteger('10000000000000000', Radix::Hexadecimal));
        self::assertSame(Invalid::IntegerLiteralOverflow, $literal->type());
        self::assertSame('18446744073709551616', $literal->value->value());
        self::assertSame('0x10000000000000000', $literal->toString());
    }

    public function testTypeDetectsTheRejectedNegationOfTheMinimumHexadecimalInteger(): void
    {
        $literal = new SqliteInteger(new UnsignedInteger('8000000000000000', Radix::Hexadecimal), negative: true);
        self::assertSame(Invalid::IntegerLiteralOverflow, $literal->type());
        self::assertSame('9223372036854775808', $literal->value->value());
        $db = new PDO('sqlite::memory:');
        $this->expectException(PDOException::class);
        $this->expectExceptionMessage('hex literal too big');
        $db->query('SELECT ' . $literal->toString());
    }

    public function testNullabilityOfAValidIntegerIsNotNull(): void
    {
        self::assertSame(Nullability::NotNull, (new SqliteInteger(new UnsignedInteger('42')))->nullability());
    }

    public function testReferencesDoesNotInventADeclarationDependency(): void
    {
        self::assertSame([], (new SqliteInteger(new UnsignedInteger('42')))->references());
    }

    public function testToStringPreservesGroupingAndTheUnaliasedResultName(): void
    {
        $db = new PDO('sqlite::memory:');
        $literal = new SqliteInteger(new UnsignedInteger('00_FF', Radix::Hexadecimal), uppercasePrefix: true);
        $result = $db->query('SELECT ' . $literal->toString());
        self::assertInstanceOf(PDOStatement::class, $result);
        self::assertSame(['0X00_FF' => 255], $result->fetch(PDO::FETCH_ASSOC));
        self::assertSame('255', $literal->value->value());
    }
}
