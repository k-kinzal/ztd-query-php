<?php

declare(strict_types=1);

namespace Tests\Unit\Sql\Value;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Stringable;
use ZtdQuery\Platform\MySql\Sql\Value\MySqlValueRenderer;
use ZtdQuery\Schema\ColumnDeclaration;
use ZtdQuery\Schema\ColumnTypeFamily;

#[UsesClass(\ZtdQuery\Platform\MySql\Sql\Value\CastTypeResolver::class)]


#[CoversClass(MySqlValueRenderer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\Sql\Value\MySqlCastRenderer::class)]
#[CoversClass(\ZtdQuery\Platform\MySql\Sql\Value\ScalarExpression::class)]
#[CoversClass(\ZtdQuery\Platform\MySql\Sql\Value\StringCoercion::class)]
final class MySqlValueRendererTest extends TestCase
{
    public function testRenderValueTextUsesHexEncodingInsteadOfSqlEscapeMode(): void
    {
        $renderer = new MySqlValueRenderer();

        self::assertSame(
            "CAST(CONVERT(X'706174685c746f5c66696c65' USING utf8mb4) AS CHAR)",
            $renderer->renderValue('path\\to\\file', new ColumnDeclaration(ColumnTypeFamily::STRING, 'VARCHAR(255)')),
        );
    }

    public function testBinaryUsesLosslessHexLiteral(): void
    {
        $renderer = new MySqlValueRenderer();

        self::assertSame(
            "CAST(X'0001ff' AS BINARY)",
            $renderer->renderValue("\x00\x01\xFF", new ColumnDeclaration(ColumnTypeFamily::BINARY, 'BLOB')),
        );
    }

    public function testNullRetainsDeclaredType(): void
    {
        $renderer = new MySqlValueRenderer();

        self::assertSame('NULL', $renderer->renderValue(null, new ColumnDeclaration(ColumnTypeFamily::INTEGER, 'INT')));
    }

    public function testInferredFloatUsesRoundTripRepresentation(): void
    {
        $renderer = new MySqlValueRenderer();

        self::assertSame('2.718281828459045', $renderer->renderValue(2.718281828459045));
    }

    public function testInferredAndDeclaredBooleansRemainDistinct(): void
    {
        $renderer = new MySqlValueRenderer();

        self::assertSame('TRUE', $renderer->renderValue(true));
        self::assertSame(
            "CAST('1' AS UNSIGNED)",
            $renderer->renderValue(true, new ColumnDeclaration(ColumnTypeFamily::BOOLEAN, 'BOOLEAN')),
        );
    }

    public function testInferredAndDeclaredIntegersRemainDistinct(): void
    {
        $renderer = new MySqlValueRenderer();

        self::assertSame('CAST(42 AS SIGNED)', $renderer->renderValue(42));
        self::assertSame(
            "CAST('42' AS SIGNED)",
            $renderer->renderValue(42, new ColumnDeclaration(ColumnTypeFamily::INTEGER, 'INT')),
        );
    }

    public function testInferredStringableUsesItsSqlRepresentation(): void
    {
        $value = new class () implements Stringable {
            public function __toString(): string
            {
                return 'CURRENT_TIMESTAMP';
            }
        };

        self::assertSame('CURRENT_TIMESTAMP', (new MySqlValueRenderer())->renderValue($value));
    }

    public function testTextEscapesSingleQuotes(): void
    {
        self::assertSame(
            "CAST('O''Reilly' AS CHAR)",
            (new MySqlValueRenderer())->renderValue("O'Reilly"),
        );
    }

    public function testStreamValueIsReadWithoutChangingItsPosition(): void
    {
        $stream = fopen('php://memory', 'r+');
        self::assertIsResource($stream);
        fwrite($stream, 'stream value');
        fseek($stream, 3);

        self::assertSame(
            "CAST('stream value' AS CHAR)",
            (new MySqlValueRenderer())->renderValue($stream),
        );
        self::assertSame(3, ftell($stream));
        fclose($stream);
    }

    public function testRejectsNonScalarValues(): void
    {
        $renderer = new MySqlValueRenderer();

        $this->expectException(RuntimeException::class);
        $renderer->renderValue([]);
    }
    public function testStoredYearZeroIsNotReinterpretedAsAStringYear(): void
    {
        $renderer = new MySqlValueRenderer();
        $year = new ColumnDeclaration(ColumnTypeFamily::INTEGER, 'year(4)');
        self::assertSame("CAST('0' AS SIGNED)", $renderer->renderValue(0, $year));
        self::assertSame("CAST('0' AS SIGNED)", $renderer->renderValue('0', $year));
        self::assertSame("CAST('1978' AS SIGNED)", $renderer->renderValue(1978, $year));
    }

    public function testStringFamilyYearRemainsTextual(): void
    {
        self::assertSame("CAST('0' AS CHAR)", (new MySqlValueRenderer())->renderValue('0', new ColumnDeclaration(ColumnTypeFamily::STRING, 'YEAR')));
    }

}
