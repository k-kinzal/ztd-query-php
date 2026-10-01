<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression;

use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Declaration\Builtin;
use SqlSemantics\Statement\Declaration\Nullability;
use SqlSemantics\Statement\Expression\SqliteBlob;
use SqlSemantics\Statement\Literal\BinaryLiteral;

#[CoversClass(SqliteBlob::class)]
#[Small]
final class SqliteBlobTest extends TestCase
{
    public function testTypeIsTheKnownLiteralStorageClass(): void
    {
        self::assertSame(Builtin::Blob, (new SqliteBlob(new BinaryLiteral("\0\xffA")))->type()->name);
    }

    public function testNullabilityIsNotNullEvenForAnEmptyValue(): void
    {
        self::assertSame(Nullability::NotNull, (new SqliteBlob(new BinaryLiteral('')))->nullability());
    }

    public function testReferencesHasNoColumnDependencies(): void
    {
        self::assertSame([], (new SqliteBlob(new BinaryLiteral("\0\xffA")))->references());
    }

    #[TestWith(["\0\xffA", "X'00ff41'"])]
    #[TestWith(['', "X''"])]
    public function testToStringPreservesTheDecodedDatabaseValueAndOutputLabel(string $value, string $sql): void
    {
        $literal = new SqliteBlob(new BinaryLiteral($value));
        self::assertSame($sql, $literal->toString());
        $db = new PDO('sqlite::memory:');
        $result = $db->query('SELECT ' . $literal->toString());
        self::assertInstanceOf(PDOStatement::class, $result);
        self::assertSame([$sql => $value], $result->fetch(PDO::FETCH_ASSOC));
    }

    public function testToStringRetainsCaseWithoutChangingTheDecodedBytes(): void
    {
        $literal = new SqliteBlob(new BinaryLiteral("\0\xffA"), true, '00Ff41');
        $db = new PDO('sqlite::memory:');
        $result = $db->query('SELECT ' . $literal->toString());
        self::assertInstanceOf(PDOStatement::class, $result);
        self::assertSame(["x'00Ff41'" => "\0\xffA"], $result->fetch(PDO::FETCH_ASSOC));
    }
}
