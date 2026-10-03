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
use SqlSemantics\Statement\Expression\SqliteText;
use SqlSemantics\Statement\Literal\StringLiteral;

#[CoversClass(SqliteText::class)]
#[Small]
final class SqliteTextTest extends TestCase
{
    public function testTypeIsTheKnownLiteralStorageClass(): void
    {
        self::assertSame(Builtin::Text, (new SqliteText(new StringLiteral("a'b\\c")))->type()->name);
    }

    public function testNullabilityIsNotNullEvenForAnEmptyValue(): void
    {
        self::assertSame(Nullability::NotNull, (new SqliteText(new StringLiteral('')))->nullability());
    }

    public function testReferencesHasNoColumnDependencies(): void
    {
        self::assertSame([], (new SqliteText(new StringLiteral("a'b\\c")))->references());
    }

    #[TestWith(["a'b\\c", "'a''b\\c'"])]
    #[TestWith(['', "''"])]
    #[TestWith(['日本語', "'日本語'"]) ]
    public function testToStringPreservesTheDecodedDatabaseValueAndOutputLabel(string $value, string $sql): void
    {
        $literal = new SqliteText(new StringLiteral($value));
        self::assertSame($sql, $literal->toString());
        $db = new PDO('sqlite::memory:');
        $result = $db->query('SELECT ' . $literal->toString());
        self::assertInstanceOf(PDOStatement::class, $result);
        self::assertSame([$sql => $value], $result->fetch(PDO::FETCH_ASSOC));
    }
}
