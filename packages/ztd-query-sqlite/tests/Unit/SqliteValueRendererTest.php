<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\Sqlite\SqliteValueRenderer;
use ZtdQuery\Schema\ColumnType;
use ZtdQuery\Schema\ColumnTypeFamily;

#[CoversClass(SqliteValueRenderer::class)]
final class SqliteValueRendererTest extends TestCase
{
    public function testIntegerStringUsesNumericExpression(): void
    {
        $renderer = new SqliteValueRenderer();

        self::assertSame(
            "CAST('42' AS INTEGER)",
            $renderer->renderValue('42', new ColumnType(ColumnTypeFamily::INTEGER, 'INTEGER')),
        );
    }

    public function testFloatUsesRoundTripRepresentation(): void
    {
        $renderer = new SqliteValueRenderer();

        self::assertSame(
            "CAST('2.718281828459045' AS REAL)",
            $renderer->renderValue(2.718281828459045, new ColumnType(ColumnTypeFamily::FLOAT, 'REAL')),
        );
    }

    public function testBinaryUsesLosslessHexLiteral(): void
    {
        $renderer = new SqliteValueRenderer();

        self::assertSame(
            "CAST(X'0001ff' AS BLOB)",
            $renderer->renderValue("\x00\x01\xFF", new ColumnType(ColumnTypeFamily::BINARY, 'BLOB')),
        );
    }

    public function testNullRetainsDeclaredType(): void
    {
        $renderer = new SqliteValueRenderer();

        self::assertSame('NULL', $renderer->renderValue(null, new ColumnType(ColumnTypeFamily::TEXT, 'TEXT')));
    }

    public function testTextRemainsText(): void
    {
        $renderer = new SqliteValueRenderer();

        self::assertSame("CAST('O''Reilly' AS TEXT)", $renderer->renderValue("O'Reilly"));
    }
}
