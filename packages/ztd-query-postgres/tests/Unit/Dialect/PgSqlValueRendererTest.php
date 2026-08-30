<?php

declare(strict_types=1);

namespace Tests\Unit\Dialect;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Stringable;
use Tests\Fixture\DriverAnswer;
use ZtdQuery\Platform\Postgres\Dialect\PgSqlValueRenderer;
use ZtdQuery\Schema\ColumnDeclaration;
use ZtdQuery\Schema\ColumnTypeFamily;

#[CoversClass(PgSqlValueRenderer::class)]
#[UsesClass(\ZtdQuery\Platform\Postgres\Dialect\PgSqlCastRenderer::class)]
final class PgSqlValueRendererTest extends TestCase
{
    public function testFalseUsesBooleanLiteral(): void
    {
        $renderer = new PgSqlValueRenderer();

        self::assertSame(
            "CAST('0' AS BOOLEAN)",
            $renderer->renderValue(false, new ColumnDeclaration(ColumnTypeFamily::BOOLEAN, 'BOOLEAN')),
        );
    }

    public function testBigIntRetainsNativeWidth(): void
    {
        $renderer = new PgSqlValueRenderer();

        self::assertSame(
            "CAST('9223372036854775807' AS BIGINT)",
            $renderer->renderValue(9223372036854775807, new ColumnDeclaration(ColumnTypeFamily::INTEGER, 'BIGINT')),
        );
    }

    public function testArrayRetainsNativeArrayType(): void
    {
        $renderer = new PgSqlValueRenderer();

        self::assertSame(
            "CAST('{1,2,3}' AS INT4[])",
            $renderer->renderValue('{1,2,3}', new ColumnDeclaration(ColumnTypeFamily::INTEGER, 'INT4[]')),
        );
    }

    public function testBinaryUsesDecodeExpression(): void
    {
        $renderer = new PgSqlValueRenderer();

        self::assertSame(
            "CAST(decode('0001ff', 'hex') AS BYTEA)",
            $renderer->renderValue("\x00\x01\xFF", new ColumnDeclaration(ColumnTypeFamily::BINARY, 'BYTEA')),
        );
    }

    public function testStringQuotesApostrophes(): void
    {
        $renderer = new PgSqlValueRenderer();

        self::assertSame("CAST('O''Reilly' AS TEXT)", $renderer->renderValue("O'Reilly"));
    }

    public function testInferredScalarTypesUseNativeLiterals(): void
    {
        $renderer = new PgSqlValueRenderer();

        self::assertSame('TRUE', $renderer->renderValue(true));
        self::assertSame('2.718281828459045', $renderer->renderValue(2.718281828459045));
        self::assertSame('CAST(42 AS INTEGER)', $renderer->renderValue(42));
    }

    public function testDeclaredFloatUsesQuotedRoundTripRepresentation(): void
    {
        $renderer = new PgSqlValueRenderer();

        self::assertSame(
            "CAST('2.718281828459045' AS DOUBLE PRECISION)",
            $renderer->renderValue(2.718281828459045, new ColumnDeclaration(ColumnTypeFamily::DOUBLE, 'DOUBLE PRECISION')),
        );
    }

    public function testInferredAndDeclaredStringableRemainDistinct(): void
    {
        $value = new class () implements Stringable {
            public function __toString(): string
            {
                return 'CURRENT_TIMESTAMP';
            }
        };
        $renderer = new PgSqlValueRenderer();

        self::assertSame('CURRENT_TIMESTAMP', $renderer->renderValue($value));
        self::assertSame(
            "CAST('CURRENT_TIMESTAMP' AS TEXT)",
            $renderer->renderValue($value, new ColumnDeclaration(ColumnTypeFamily::TEXT, 'TEXT')),
        );
    }

    public function testUntypedArrayIsRejected(): void
    {
        $this->expectException(RuntimeException::class);

        (new PgSqlValueRenderer())->renderValue(['value']);
    }

    public function testDeclaredNonScalarUsesSerializedRepresentation(): void
    {
        self::assertSame(
            "CAST('a:1:{i:0;s:5:\"value\";}' AS JSONB)",
            (new PgSqlValueRenderer())->renderValue(['value'], new ColumnDeclaration(ColumnTypeFamily::JSON, 'JSONB')),
        );
    }

    public function testBinaryStreamIsReadWithoutChangingItsPosition(): void
    {
        $stream = fopen('php://memory', 'r+');
        self::assertIsResource($stream);
        fwrite($stream, "\x00\x01\xFF");
        fseek($stream, 1);

        self::assertSame(
            "CAST(decode('0001ff', 'hex') AS BYTEA)",
            (new PgSqlValueRenderer())->renderValue($stream, new ColumnDeclaration(ColumnTypeFamily::BINARY, 'BYTEA')),
        );
        self::assertSame(1, ftell($stream));
        fclose($stream);
    }

    public function testBinaryRejectsNonStringableNonResource(): void
    {
        $this->expectException(RuntimeException::class);

        (new PgSqlValueRenderer())->renderValue(
            ['value'],
            new ColumnDeclaration(ColumnTypeFamily::BINARY, 'BYTEA'),
        );
    }
    public function testRenderExpressionWritesAStringAsAQuotedLiteral(): void
    {
        self::assertSame(
            "'a'",
            (new PgSqlValueRenderer())->renderExpression('a', new ColumnDeclaration(ColumnTypeFamily::STRING, 'VARCHAR'), false),
        );
    }

    public function testRenderExpressionKeepsABackslashInTheLiteralItWrites(): void
    {
        self::assertSame(
            "'a\\b'",
            (new PgSqlValueRenderer())->renderExpression('a\\b', new ColumnDeclaration(ColumnTypeFamily::STRING, 'VARCHAR'), false),
        );
    }

    public function testRenderExpressionWritesBytesAsAHexLiteral(): void
    {
        self::assertSame(
            "decode('6162', 'hex')",
            (new PgSqlValueRenderer())->renderExpression('ab', new ColumnDeclaration(ColumnTypeFamily::BINARY, 'BYTEA'), true),
        );
    }

    public function testInferTypeReadsAWholeNumberAsAnInteger(): void
    {
        self::assertSame(ColumnTypeFamily::INTEGER, (new PgSqlValueRenderer())->inferType(1)->family);
    }

    public function testInferTypeReadsAnythingElseAsAString(): void
    {
        self::assertSame(ColumnTypeFamily::TEXT, (new PgSqlValueRenderer())->inferType('a')->family);
    }

    public function testStringValueAnswersTheBytesAValueIs(): void
    {
        self::assertSame('1', (new PgSqlValueRenderer())->stringValue(1));
    }

    public function testStringValueRefusesAValueNoLiteralCanCarry(): void
    {
        $this->expectException(RuntimeException::class);

        (new PgSqlValueRenderer())->renderValue(DriverAnswer::unsupported());
    }

    public function testReadStreamAnswersEverythingTheStreamHolds(): void
    {
        $stream = fopen('php://memory', 'r+');
        self::assertIsResource($stream);
        fwrite($stream, 'abc');

        self::assertSame('abc', (new PgSqlValueRenderer())->readStream($stream));
    }

    public function testReadStreamLeavesTheStreamWhereTheCallerHadIt(): void
    {
        $stream = fopen('php://memory', 'r+');
        self::assertIsResource($stream);
        fwrite($stream, 'abc');
        fseek($stream, 1);

        (new PgSqlValueRenderer())->readStream($stream);

        self::assertSame(1, ftell($stream));
    }

    public function testQuoteValueDoublesEveryQuoteInTheBytes(): void
    {
        self::assertSame("'it''s'", (new PgSqlValueRenderer())->quoteValue("it's"));
    }

    public function testRenderValueWritesANullAsNull(): void
    {
        self::assertSame('NULL', (new PgSqlValueRenderer())->renderValue(null));
    }

    public function testIsRenderableReportsAValueALiteralCanCarry(): void
    {
        self::assertTrue((new PgSqlValueRenderer())->isRenderable(DriverAnswer::renderable()));
    }

    public function testIsRenderableIsFalseForSomethingNoLiteralCouldCarry(): void
    {
        self::assertFalse((new PgSqlValueRenderer())->isRenderable(DriverAnswer::unsupported()));
    }
}
