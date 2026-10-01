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
use SqlSemantics\Statement\Expression\SqliteCurrentTime;

#[CoversClass(SqliteCurrentTime::class)]
#[Small]
final class SqliteCurrentTimeTest extends TestCase
{
    public function testTypeIsTextRatherThanASchemaDeclaredTimestamp(): void
    {
        self::assertSame(Builtin::Text, SqliteCurrentTime::Timestamp->type()->name);
    }

    public function testNullabilityIsNotNull(): void
    {
        self::assertSame(Nullability::NotNull, SqliteCurrentTime::Date->nullability());
    }

    public function testReferencesHasNoDeclarationDependency(): void
    {
        self::assertSame([], SqliteCurrentTime::Time->references());
    }

    #[TestWith([SqliteCurrentTime::Date, '/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D'])]
    #[TestWith([SqliteCurrentTime::Time, '/^[0-9]{2}:[0-9]{2}:[0-9]{2}$/D'])]
    #[TestWith([SqliteCurrentTime::Timestamp, '/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$/D'])]
    public function testToStringRequestsTheClockAtDatabaseExecutionTime(SqliteCurrentTime $clock, string $pattern): void
    {
        $result = (new PDO('sqlite::memory:'))->query('SELECT ' . $clock->toString());
        self::assertInstanceOf(PDOStatement::class, $result);
        $value = $result->fetchColumn();
        self::assertIsString($value);
        self::assertMatchesRegularExpression($pattern, $value);
    }
}
