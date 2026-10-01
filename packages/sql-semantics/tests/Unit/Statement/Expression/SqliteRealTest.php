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
use SqlSemantics\Statement\Expression\SqliteReal;

#[CoversClass(SqliteReal::class)]
#[Small]
final class SqliteRealTest extends TestCase
{
    #[TestWith(['1.0'])]
    #[TestWith(['.5'])]
    #[TestWith(['1.'])]
    #[TestWith(['1_000.25e-2'])]
    #[TestWith(['1e9999'])]
    #[TestWith(['1e-9999'])]
    public function testTypeAlwaysUsesRealStorageIncludingUnderflowAndOverflow(string $numeral): void
    {
        $literal = new SqliteReal($numeral);
        $db = new PDO('sqlite::memory:');
        $result = $db->query('SELECT typeof(' . $literal->toString() . ')');
        self::assertInstanceOf(PDOStatement::class, $result);
        self::assertSame('real', $result->fetchColumn());
        self::assertSame(Builtin::DoublePrecision, $literal->type()->name);
    }

    public function testNullabilityIsNotNull(): void
    {
        self::assertSame(Nullability::NotNull, (new SqliteReal('1e9999'))->nullability());
    }

    public function testReferencesHasNoDeclarationDependency(): void
    {
        self::assertSame([], (new SqliteReal('.1'))->references());
    }

    public function testToStringPreservesDigitGroupingAndKeepsTheExactDecimalValue(): void
    {
        $literal = new SqliteReal('1_000.3_0E+0_2');
        self::assertSame('1_000.3_0E+0_2', $literal->toString());
        self::assertSame('1000.30E+02', $literal->value->value());
        $result = (new PDO('sqlite::memory:'))->query('SELECT ' . $literal->toString());
        self::assertInstanceOf(PDOStatement::class, $result);
        self::assertSame(['1_000.3_0E+0_2' => 100030.0], $result->fetch(PDO::FETCH_ASSOC));
    }
}
