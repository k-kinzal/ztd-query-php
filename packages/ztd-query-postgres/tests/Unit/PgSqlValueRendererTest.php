<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\Postgres\PgSqlValueRenderer;
use ZtdQuery\Schema\ColumnType;
use ZtdQuery\Schema\ColumnTypeFamily;

#[CoversClass(PgSqlValueRenderer::class)]
final class PgSqlValueRendererTest extends TestCase
{
    public function testFalseUsesBooleanLiteral(): void
    {
        $renderer = new PgSqlValueRenderer();

        self::assertSame(
            "CAST('0' AS BOOLEAN)",
            $renderer->renderValue(false, new ColumnType(ColumnTypeFamily::BOOLEAN, 'BOOLEAN')),
        );
    }

    public function testBigIntRetainsNativeWidth(): void
    {
        $renderer = new PgSqlValueRenderer();

        self::assertSame(
            "CAST('9223372036854775807' AS BIGINT)",
            $renderer->renderValue(9223372036854775807, new ColumnType(ColumnTypeFamily::INTEGER, 'BIGINT')),
        );
    }

    public function testArrayRetainsNativeArrayType(): void
    {
        $renderer = new PgSqlValueRenderer();

        self::assertSame(
            "CAST('{1,2,3}' AS INT4[])",
            $renderer->renderValue('{1,2,3}', new ColumnType(ColumnTypeFamily::INTEGER, 'INT4[]')),
        );
    }

    public function testBinaryUsesDecodeExpression(): void
    {
        $renderer = new PgSqlValueRenderer();

        self::assertSame(
            "CAST(decode('0001ff', 'hex') AS BYTEA)",
            $renderer->renderValue("\x00\x01\xFF", new ColumnType(ColumnTypeFamily::BINARY, 'BYTEA')),
        );
    }

    public function testStringQuotesApostrophes(): void
    {
        $renderer = new PgSqlValueRenderer();

        self::assertSame("CAST('O''Reilly' AS TEXT)", $renderer->renderValue("O'Reilly"));
    }
}
