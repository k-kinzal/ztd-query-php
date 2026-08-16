<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\MySqlValueRenderer;
use ZtdQuery\Schema\ColumnType;
use ZtdQuery\Schema\ColumnTypeFamily;

#[CoversClass(MySqlValueRenderer::class)]
final class MySqlValueRendererTest extends TestCase
{
    public function testTextUsesHexEncodingInsteadOfSqlEscapeMode(): void
    {
        $renderer = new MySqlValueRenderer();

        self::assertSame(
            "CAST(CONVERT(X'706174685c746f5c66696c65' USING utf8mb4) AS CHAR)",
            $renderer->renderValue('path\\to\\file', new ColumnType(ColumnTypeFamily::STRING, 'VARCHAR(255)')),
        );
    }

    public function testBinaryUsesLosslessHexLiteral(): void
    {
        $renderer = new MySqlValueRenderer();

        self::assertSame(
            "CAST(X'0001ff' AS BINARY)",
            $renderer->renderValue("\x00\x01\xFF", new ColumnType(ColumnTypeFamily::BINARY, 'BLOB')),
        );
    }

    public function testNullRetainsDeclaredType(): void
    {
        $renderer = new MySqlValueRenderer();

        self::assertSame('NULL', $renderer->renderValue(null, new ColumnType(ColumnTypeFamily::INTEGER, 'INT')));
    }

    public function testInferredFloatUsesRoundTripRepresentation(): void
    {
        $renderer = new MySqlValueRenderer();

        self::assertSame('2.718281828459045', $renderer->renderValue(2.718281828459045));
    }

    public function testRejectsNonScalarValues(): void
    {
        $renderer = new MySqlValueRenderer();

        $this->expectException(\RuntimeException::class);
        $renderer->renderValue([]);
    }
}
