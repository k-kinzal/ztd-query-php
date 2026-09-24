<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Scalar\Text;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Scalar\Text\TrimBinder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(TrimBinder::class)]
#[Medium]
final class TrimBinderTest extends TestCase
{
    #[TestWith(['mysql-5.6.51', "SELECT TRIM('b')", "SELECT TRIM(BOTH FROM 'b')"])]
    #[TestWith(['mysql-5.7.44', "SELECT TRIM('a' FROM 'b')", "SELECT TRIM(BOTH 'a' FROM 'b')"])]
    #[TestWith(['mysql-8.1.0', "SELECT TRIM(LEADING 'a' FROM 'b')", "SELECT TRIM(LEADING 'a' FROM 'b')"])]
    #[TestWith(['mysql-8.4.7', "SELECT TRIM(TRAILING FROM 'b')", "SELECT TRIM(TRAILING FROM 'b')"])]
    #[TestWith(['mysql-9.1.0', "SELECT TRIM(BOTH 'a' FROM 'b')", "SELECT TRIM(BOTH 'a' FROM 'b')"])]
    #[TestWith(['mysql-8.1.0', 'KILL CONNECTION (TRIM(1 IS NOT FALSE FROM 2) + INTERVAL 1 DAY)', 'KILL CONNECTION DATE_ADD(TRIM(BOTH(1 IS NOT FALSE) FROM 2), INTERVAL 1 DAY)'])]
    public function testBindKeepsTheMySqlSideRemovedCharactersAndString(string $release, string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $release))->build());
        self::assertSame($expected, $binder->bind($sql)->toString());
        self::assertSame($expected, $binder->bind($expected)->toString());
    }

    #[TestWith(["SELECT TRIM(LEADING 'a' FROM 'b')", "SELECT TRIM(LEADING 'a' FROM 'b')"])]
    #[TestWith(["SELECT TRIM('b', 'a')", "SELECT TRIM(BOTH 'a' FROM 'b')"])]
    #[TestWith(["SELECT TRIM(TRAILING FROM 'b', 'a')", "SELECT TRIM(TRAILING 'a' FROM 'b')"])]
    #[TestWith(["SELECT TRIM('b')", "SELECT TRIM(BOTH FROM 'b')"])]
    public function testBindReadsThePostgreSqlArgumentListAsStringThenCharacters(string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        self::assertSame($expected, $binder->bind($sql)->toString());
        self::assertSame($expected, $binder->bind($expected)->toString());
    }

    public function testBindDiagnosesTooManyPostgreSqlOperands(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::FunctionArity->message());
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("SELECT TRIM(TRAILING 'b' FROM 'c', 'd')");
    }

    #[TestWith([Dialect::PostgreSql, 'SELECT "trim"(\'a\')', 'SELECT "trim"(\'a\')'])]
    #[TestWith([Dialect::Sqlite, "SELECT trim('a', 'b')", "SELECT trim('a', 'b')"])]
    public function testBindLeavesOrdinaryFunctionsToSignatureResolution(Dialect $dialect, string $sql, string $expected): void
    {
        self::assertSame($expected, (new Binder((new SchemaBuilder($dialect))->build()))->bind($sql)->toString());
    }
}
