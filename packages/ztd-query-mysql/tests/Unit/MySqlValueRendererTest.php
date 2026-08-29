<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Stringable;
use Tests\Fixture\DriverAnswer;
use ZtdQuery\Platform\MySql\MySqlValueRenderer;
use ZtdQuery\Schema\ColumnDeclaration;
use ZtdQuery\Schema\ColumnTypeFamily;

#[CoversClass(MySqlValueRenderer::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlCastRenderer::class)]
final class MySqlValueRendererTest extends TestCase
{
    public function testTextUsesHexEncodingInsteadOfSqlEscapeMode(): void
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
        $renderer->renderValue(DriverAnswer::unsupported());
    }
    public function testRenderExpressionWritesAStringAsAQuotedLiteral(): void
    {
        self::assertSame(
            "'a'",
            (new MySqlValueRenderer())->renderExpression('a', new ColumnDeclaration(ColumnTypeFamily::STRING, 'VARCHAR'), false),
        );
    }

    public function testRenderExpressionWritesABackslashAsBytesSoTheServerSettingCannotChangeIt(): void
    {
        self::assertStringStartsWith(
            "CONVERT(X'",
            (new MySqlValueRenderer())->renderExpression('a\\b', new ColumnDeclaration(ColumnTypeFamily::STRING, 'VARCHAR'), false),
        );
    }

    public function testRenderExpressionWritesBytesAsAHexLiteral(): void
    {
        self::assertSame(
            "X'6162'",
            (new MySqlValueRenderer())->renderExpression('ab', new ColumnDeclaration(ColumnTypeFamily::BINARY, 'BLOB'), true),
        );
    }

    public function testInferTypeReadsAWholeNumberAsAnInteger(): void
    {
        self::assertSame(ColumnTypeFamily::INTEGER, (new MySqlValueRenderer())->inferType(1)->family);
    }

    public function testInferTypeReadsAnythingElseAsAString(): void
    {
        self::assertSame(ColumnTypeFamily::STRING, (new MySqlValueRenderer())->inferType('a')->family);
    }

    public function testStringValueAnswersTheBytesAValueIs(): void
    {
        self::assertSame('1', (new MySqlValueRenderer())->stringValue(1));
    }

    public function testStringValueRefusesAValueNoLiteralCanCarry(): void
    {
        $this->expectException(RuntimeException::class);

        (new MySqlValueRenderer())->renderValue(DriverAnswer::unsupported());
    }

    public function testReadStreamAnswersEverythingTheStreamHolds(): void
    {
        $stream = fopen('php://memory', 'r+');
        self::assertIsResource($stream);
        fwrite($stream, 'abc');

        self::assertSame('abc', (new MySqlValueRenderer())->readStream($stream));
    }

    public function testReadStreamLeavesTheStreamWhereTheCallerHadIt(): void
    {
        $stream = fopen('php://memory', 'r+');
        self::assertIsResource($stream);
        fwrite($stream, 'abc');
        fseek($stream, 1);

        (new MySqlValueRenderer())->readStream($stream);

        self::assertSame(1, ftell($stream));
    }

    public function testQuoteValueDoublesEveryQuoteInTheBytes(): void
    {
        self::assertSame("'it''s'", (new MySqlValueRenderer())->quoteValue("it's"));
    }

    public function testRenderValueWritesANullAsNull(): void
    {
        self::assertSame('NULL', (new MySqlValueRenderer())->renderValue(null));
    }

    public function testIsRenderableReportsAValueALiteralCanCarry(): void
    {
        self::assertTrue((new MySqlValueRenderer())->isRenderable(DriverAnswer::renderable()));
    }

    public function testIsRenderableIsFalseForSomethingNoLiteralCouldCarry(): void
    {
        self::assertFalse((new MySqlValueRenderer())->isRenderable(DriverAnswer::unsupported()));
    }

}
