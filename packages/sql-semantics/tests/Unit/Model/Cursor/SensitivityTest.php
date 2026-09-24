<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Cursor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Cursor\Sensitivity;
use SqlSemantics\Model\Statement\Cursor\DeclareCursorStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Sensitivity::class)]
#[Medium]
final class SensitivityTest extends TestCase
{
    public function testRepresentsEverySensitivityPolicy(): void
    {
        self::assertSame(['', 'ASENSITIVE', 'INSENSITIVE'], array_column(Sensitivity::cases(), 'value'));
    }

    #[TestWith(['DECLARE cur CURSOR FOR SELECT 1', Sensitivity::Default, 'DECLARE "cur" CURSOR FOR SELECT 1'])]
    #[TestWith(['DECLARE cur ASENSITIVE CURSOR FOR SELECT 1', Sensitivity::Asensitive, 'DECLARE "cur" ASENSITIVE CURSOR FOR SELECT 1'])]
    #[TestWith(['DECLARE cur INSENSITIVE CURSOR FOR SELECT 1', Sensitivity::Insensitive, 'DECLARE "cur" INSENSITIVE CURSOR FOR SELECT 1'])]
    public function testClassifiesTheDeclaredSensitivity(string $sql, Sensitivity $sensitivity, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind($sql);
        self::assertInstanceOf(DeclareCursorStatement::class, $statement);
        self::assertSame($sensitivity, $statement->sensitivity);
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected)->toString());
    }
}
