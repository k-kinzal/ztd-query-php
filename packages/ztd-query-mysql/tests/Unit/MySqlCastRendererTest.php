<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Contract\CastRendererContractTest;
use ZtdQuery\Platform\CastRenderer;
use ZtdQuery\Platform\MySql\MySqlCastRenderer;
use ZtdQuery\Schema\ColumnType;
use ZtdQuery\Schema\ColumnTypeFamily;

#[CoversClass(MySqlCastRenderer::class)]
final class MySqlCastRendererTest extends CastRendererContractTest
{
    protected function createRenderer(): CastRenderer
    {
        return new MySqlCastRenderer();
    }

    #[\Override]
    protected function nativeTypeFor(ColumnTypeFamily $family): string
    {
        return match ($family) {
            ColumnTypeFamily::INTEGER => 'INT',
            ColumnTypeFamily::FLOAT => 'FLOAT',
            ColumnTypeFamily::DOUBLE => 'DOUBLE',
            ColumnTypeFamily::DECIMAL => 'DECIMAL(10,2)',
            ColumnTypeFamily::STRING => 'VARCHAR(255)',
            ColumnTypeFamily::TEXT => 'TEXT',
            ColumnTypeFamily::BOOLEAN => 'TINYINT(1)',
            ColumnTypeFamily::DATE => 'DATE',
            ColumnTypeFamily::TIME => 'TIME',
            ColumnTypeFamily::DATETIME => 'DATETIME',
            ColumnTypeFamily::TIMESTAMP => 'TIMESTAMP',
            ColumnTypeFamily::BINARY => 'BLOB',
            ColumnTypeFamily::JSON => 'JSON',
            ColumnTypeFamily::UNKNOWN => 'GEOMETRY',
        };
    }

    public function testRenderCastInteger(): void
    {
        $renderer = new MySqlCastRenderer();
        $type = new ColumnType(ColumnTypeFamily::INTEGER, 'INT');
        $result = $renderer->renderCast('1', $type);
        self::assertSame('CAST(1 AS SIGNED)', $result);
    }

    public function testRenderCastString(): void
    {
        $type = new ColumnType(ColumnTypeFamily::STRING, 'VARCHAR(255)');
        $result = (new MySqlCastRenderer())->renderCast("'Alice'", $type);
        self::assertSame("CAST('Alice' AS CHAR)", $result);
    }

    public function testRenderCastText(): void
    {
        $type = new ColumnType(ColumnTypeFamily::TEXT, 'TEXT');
        $result = (new MySqlCastRenderer())->renderCast("'content'", $type);
        self::assertSame("CAST('content' AS CHAR)", $result);
    }

    public function testRenderCastDecimalWithPrecision(): void
    {
        $type = new ColumnType(ColumnTypeFamily::DECIMAL, 'DECIMAL(10,2)');
        $result = (new MySqlCastRenderer())->renderCast("'99.99'", $type);
        self::assertSame("CAST('99.99' AS DECIMAL(10,2))", $result);
    }

    public function testRenderCastDecimalWithoutPrecision(): void
    {
        $type = new ColumnType(ColumnTypeFamily::DECIMAL, 'DECIMAL');
        $result = (new MySqlCastRenderer())->renderCast("'99.99'", $type);
        self::assertSame("CAST('99.99' AS DECIMAL(65,30))", $result);
    }

    public function testRenderCastDecimalWithSinglePrecision(): void
    {
        $type = new ColumnType(ColumnTypeFamily::DECIMAL, 'DECIMAL(10)');
        $result = (new MySqlCastRenderer())->renderCast("'99'", $type);
        self::assertSame("CAST('99' AS DECIMAL(10,0))", $result);
    }

    public function testRenderCastFloat(): void
    {
        $type = new ColumnType(ColumnTypeFamily::FLOAT, 'FLOAT');
        $result = (new MySqlCastRenderer())->renderCast("'1.5'", $type);
        self::assertSame("CAST('1.5' AS FLOAT)", $result);
    }

    public function testRenderCastDouble(): void
    {
        $type = new ColumnType(ColumnTypeFamily::DOUBLE, 'DOUBLE');
        $result = (new MySqlCastRenderer())->renderCast("'1.5'", $type);
        self::assertSame("CAST('1.5' AS DOUBLE)", $result);
    }

    public function testRenderCastBoolean(): void
    {
        $type = new ColumnType(ColumnTypeFamily::BOOLEAN, 'BOOLEAN');
        $result = (new MySqlCastRenderer())->renderCast("'1'", $type);
        self::assertSame("CAST('1' AS UNSIGNED)", $result);
    }

    public function testRenderCastDate(): void
    {
        $type = new ColumnType(ColumnTypeFamily::DATE, 'DATE');
        $result = (new MySqlCastRenderer())->renderCast("'2024-01-01'", $type);
        self::assertSame("CAST('2024-01-01' AS DATE)", $result);
    }

    public function testRenderCastDatetime(): void
    {
        $type = new ColumnType(ColumnTypeFamily::DATETIME, 'DATETIME');
        $result = (new MySqlCastRenderer())->renderCast("'2024-01-01 00:00:00'", $type);
        self::assertSame("CAST('2024-01-01 00:00:00' AS DATETIME)", $result);
    }

    public function testRenderCastTimestamp(): void
    {
        $type = new ColumnType(ColumnTypeFamily::TIMESTAMP, 'TIMESTAMP');
        $result = (new MySqlCastRenderer())->renderCast("'2024-01-01 00:00:00'", $type);
        self::assertSame("CAST('2024-01-01 00:00:00' AS DATETIME)", $result);
    }

    public function testRenderCastTime(): void
    {
        $type = new ColumnType(ColumnTypeFamily::TIME, 'TIME');
        $result = (new MySqlCastRenderer())->renderCast("'12:00:00'", $type);
        self::assertSame("CAST('12:00:00' AS TIME)", $result);
    }

    public function testRenderCastJson(): void
    {
        $type = new ColumnType(ColumnTypeFamily::JSON, 'JSON');
        $result = (new MySqlCastRenderer())->renderCast("'{}'", $type);
        self::assertSame("CAST('{}' AS JSON)", $result);
    }

    public function testRenderCastBinary(): void
    {
        $type = new ColumnType(ColumnTypeFamily::BINARY, 'BINARY');
        $result = (new MySqlCastRenderer())->renderCast("'data'", $type);
        self::assertSame("CAST('data' AS BINARY)", $result);
    }

    public function testRenderNullCastInteger(): void
    {
        $type = new ColumnType(ColumnTypeFamily::INTEGER, 'INT');
        $result = (new MySqlCastRenderer())->renderNullCast($type);
        self::assertSame('CAST(NULL AS SIGNED)', $result);
    }

    public function testRenderNullCastString(): void
    {
        $type = new ColumnType(ColumnTypeFamily::STRING, 'VARCHAR(255)');
        $result = (new MySqlCastRenderer())->renderNullCast($type);
        self::assertSame('CAST(NULL AS CHAR)', $result);
    }

    public function testRenderNullCastJson(): void
    {
        $type = new ColumnType(ColumnTypeFamily::JSON, 'JSON');
        $result = (new MySqlCastRenderer())->renderNullCast($type);
        self::assertSame('CAST(NULL AS JSON)', $result);
    }

    public function testRenderCastUnknownFamilyFallsBackToNativeType(): void
    {
        $type = new ColumnType(ColumnTypeFamily::UNKNOWN, 'YEAR');
        $result = (new MySqlCastRenderer())->renderCast("'2024'", $type);
        self::assertSame("CAST('2024' AS YEAR)", $result);
    }

    public function testRenderCastUnknownFamilyUnknownNativeType(): void
    {
        $type = new ColumnType(ColumnTypeFamily::UNKNOWN, 'CUSTOM_TYPE');
        $result = (new MySqlCastRenderer())->renderCast("'value'", $type);
        self::assertSame("CAST('value' AS CHAR)", $result);
    }

    /**
     * P-CR-1: Non-empty output for all families.
     */
    #[DataProvider('providerAllFamilies')]
    public function testAllColumnTypeFamiliesProduceNonEmptyOutput(ColumnTypeFamily $family): void
    {
        $type = new ColumnType($family, 'TEST_TYPE');
        $result = (new MySqlCastRenderer())->renderNullCast($type);
        self::assertNotEmpty($result, "renderNullCast() returned empty for family {$family->value}");
    }

    /**
     * P-CR-2: CAST keyword presence.
     */
    #[DataProvider('providerAllFamilies')]
    public function testOutputContainsCastKeyword(ColumnTypeFamily $family): void
    {
        $type = new ColumnType($family, 'TEST_TYPE');
        $result = (new MySqlCastRenderer())->renderNullCast($type);
        self::assertStringContainsString('CAST(', $result, "renderNullCast() missing CAST keyword for family {$family->value}");
    }

    /**
     * P-CR-4: Determinism.
     */
    public function testRenderCastIsDeterministic(): void
    {
        $type = new ColumnType(ColumnTypeFamily::INTEGER, 'INT');
        $result1 = (new MySqlCastRenderer())->renderCast('42', $type);
        $result2 = (new MySqlCastRenderer())->renderCast('42', $type);
        self::assertSame($result1, $result2);
    }

    /**
     * P-CR-3: NULL preservation.
     */
    #[DataProvider('providerAllFamilies')]
    public function testRenderNullCastContainsNull(ColumnTypeFamily $family): void
    {
        $type = new ColumnType($family, 'TEST_TYPE');
        $result = (new MySqlCastRenderer())->renderNullCast($type);
        self::assertStringContainsString('NULL', $result, "renderNullCast() missing NULL for family {$family->value}");
    }

    public function testUnknownNativeIntMapsToSigned(): void
    {
        $type = new ColumnType(ColumnTypeFamily::UNKNOWN, 'INT');
        self::assertSame("CAST('1' AS SIGNED)", (new MySqlCastRenderer())->renderCast("'1'", $type));
    }

    public function testUnknownNativeIntegerMapsToSigned(): void
    {
        $type = new ColumnType(ColumnTypeFamily::UNKNOWN, 'INTEGER');
        self::assertSame("CAST('1' AS SIGNED)", (new MySqlCastRenderer())->renderCast("'1'", $type));
    }

    public function testUnknownNativeTinyintMapsToSigned(): void
    {
        $type = new ColumnType(ColumnTypeFamily::UNKNOWN, 'TINYINT');
        self::assertSame("CAST('1' AS SIGNED)", (new MySqlCastRenderer())->renderCast("'1'", $type));
    }

    public function testUnknownNativeSmallintMapsToSigned(): void
    {
        $type = new ColumnType(ColumnTypeFamily::UNKNOWN, 'SMALLINT');
        self::assertSame("CAST('1' AS SIGNED)", (new MySqlCastRenderer())->renderCast("'1'", $type));
    }

    public function testUnknownNativeMediumintMapsToSigned(): void
    {
        $type = new ColumnType(ColumnTypeFamily::UNKNOWN, 'MEDIUMINT');
        self::assertSame("CAST('1' AS SIGNED)", (new MySqlCastRenderer())->renderCast("'1'", $type));
    }

    public function testUnknownNativeBigintMapsToSigned(): void
    {
        $type = new ColumnType(ColumnTypeFamily::UNKNOWN, 'BIGINT');
        self::assertSame("CAST('1' AS SIGNED)", (new MySqlCastRenderer())->renderCast("'1'", $type));
    }

    public function testUnknownNativeDecimalMapsToDecimal(): void
    {
        $type = new ColumnType(ColumnTypeFamily::UNKNOWN, 'DECIMAL(10,2)');
        self::assertSame("CAST('1' AS DECIMAL(10,2))", (new MySqlCastRenderer())->renderCast("'1'", $type));
    }

    public function testUnknownNativeNumericMapsToDecimal(): void
    {
        $type = new ColumnType(ColumnTypeFamily::UNKNOWN, 'NUMERIC');
        self::assertSame("CAST('1' AS DECIMAL(65,30))", (new MySqlCastRenderer())->renderCast("'1'", $type));
    }

    public function testUnknownNativeFloatMapsToFloat(): void
    {
        $type = new ColumnType(ColumnTypeFamily::UNKNOWN, 'FLOAT');
        self::assertSame("CAST('1' AS FLOAT)", (new MySqlCastRenderer())->renderCast("'1'", $type));
    }

    public function testUnknownNativeDoubleMapsToDouble(): void
    {
        $type = new ColumnType(ColumnTypeFamily::UNKNOWN, 'DOUBLE');
        self::assertSame("CAST('1' AS DOUBLE)", (new MySqlCastRenderer())->renderCast("'1'", $type));
    }

    public function testUnknownNativeRealMapsToDouble(): void
    {
        $type = new ColumnType(ColumnTypeFamily::UNKNOWN, 'REAL');
        self::assertSame("CAST('1' AS DOUBLE)", (new MySqlCastRenderer())->renderCast("'1'", $type));
    }

    public function testUnknownNativeDateMapsToDate(): void
    {
        $type = new ColumnType(ColumnTypeFamily::UNKNOWN, 'DATE');
        self::assertSame("CAST('1' AS DATE)", (new MySqlCastRenderer())->renderCast("'1'", $type));
    }

    public function testUnknownNativeDatetimeMapsToDatetime(): void
    {
        $type = new ColumnType(ColumnTypeFamily::UNKNOWN, 'DATETIME');
        self::assertSame("CAST('1' AS DATETIME)", (new MySqlCastRenderer())->renderCast("'1'", $type));
    }

    public function testUnknownNativeTimestampMapsToDatetime(): void
    {
        $type = new ColumnType(ColumnTypeFamily::UNKNOWN, 'TIMESTAMP');
        self::assertSame("CAST('1' AS DATETIME)", (new MySqlCastRenderer())->renderCast("'1'", $type));
    }

    public function testUnknownNativeTimeMapsToTime(): void
    {
        $type = new ColumnType(ColumnTypeFamily::UNKNOWN, 'TIME');
        self::assertSame("CAST('1' AS TIME)", (new MySqlCastRenderer())->renderCast("'1'", $type));
    }

    public function testUnknownNativeJsonMapsToJson(): void
    {
        $type = new ColumnType(ColumnTypeFamily::UNKNOWN, 'JSON');
        self::assertSame("CAST('1' AS JSON)", (new MySqlCastRenderer())->renderCast("'1'", $type));
    }

    public function testUnknownNativeBinaryMapsToBinary(): void
    {
        $type = new ColumnType(ColumnTypeFamily::UNKNOWN, 'BINARY');
        self::assertSame("CAST('1' AS BINARY)", (new MySqlCastRenderer())->renderCast("'1'", $type));
    }

    public function testUnknownNativeVarbinaryMapsToBinary(): void
    {
        $type = new ColumnType(ColumnTypeFamily::UNKNOWN, 'VARBINARY');
        self::assertSame("CAST('1' AS BINARY)", (new MySqlCastRenderer())->renderCast("'1'", $type));
    }

    public function testUnknownNativeBlobMapsToBinary(): void
    {
        $type = new ColumnType(ColumnTypeFamily::UNKNOWN, 'BLOB');
        self::assertSame("CAST('1' AS BINARY)", (new MySqlCastRenderer())->renderCast("'1'", $type));
    }

    public function testUnknownNativeTinyblobMapsToBinary(): void
    {
        $type = new ColumnType(ColumnTypeFamily::UNKNOWN, 'TINYBLOB');
        self::assertSame("CAST('1' AS BINARY)", (new MySqlCastRenderer())->renderCast("'1'", $type));
    }

    public function testUnknownNativeMediumblobMapsToBinary(): void
    {
        $type = new ColumnType(ColumnTypeFamily::UNKNOWN, 'MEDIUMBLOB');
        self::assertSame("CAST('1' AS BINARY)", (new MySqlCastRenderer())->renderCast("'1'", $type));
    }

    public function testUnknownNativeLongblobMapsToBinary(): void
    {
        $type = new ColumnType(ColumnTypeFamily::UNKNOWN, 'LONGBLOB');
        self::assertSame("CAST('1' AS BINARY)", (new MySqlCastRenderer())->renderCast("'1'", $type));
    }

    public function testUnknownNativeWithParenthesesStrippedForMatch(): void
    {
        $type = new ColumnType(ColumnTypeFamily::UNKNOWN, 'INT(11)');
        self::assertSame("CAST('1' AS SIGNED)", (new MySqlCastRenderer())->renderCast("'1'", $type));
    }

    public function testRenderCastExpressionContainedInOutput(): void
    {
        $type = new ColumnType(ColumnTypeFamily::INTEGER, 'INT');
        $result = (new MySqlCastRenderer())->renderCast('my_column', $type);
        self::assertStringContainsString('my_column', $result);
    }

    public function testRenderNullCastDecimal(): void
    {
        $type = new ColumnType(ColumnTypeFamily::DECIMAL, 'DECIMAL(10,2)');
        $result = (new MySqlCastRenderer())->renderNullCast($type);
        self::assertSame('CAST(NULL AS DECIMAL(10,2))', $result);
    }

    public function testRenderNullCastFloat(): void
    {
        $type = new ColumnType(ColumnTypeFamily::FLOAT, 'FLOAT');
        $result = (new MySqlCastRenderer())->renderNullCast($type);
        self::assertSame('CAST(NULL AS FLOAT)', $result);
    }

    public function testRenderNullCastDouble(): void
    {
        $type = new ColumnType(ColumnTypeFamily::DOUBLE, 'DOUBLE');
        $result = (new MySqlCastRenderer())->renderNullCast($type);
        self::assertSame('CAST(NULL AS DOUBLE)', $result);
    }

    public function testRenderNullCastBoolean(): void
    {
        $type = new ColumnType(ColumnTypeFamily::BOOLEAN, 'BOOLEAN');
        $result = (new MySqlCastRenderer())->renderNullCast($type);
        self::assertSame('CAST(NULL AS UNSIGNED)', $result);
    }

    public function testRenderNullCastDate(): void
    {
        $type = new ColumnType(ColumnTypeFamily::DATE, 'DATE');
        $result = (new MySqlCastRenderer())->renderNullCast($type);
        self::assertSame('CAST(NULL AS DATE)', $result);
    }

    public function testRenderNullCastDatetime(): void
    {
        $type = new ColumnType(ColumnTypeFamily::DATETIME, 'DATETIME');
        $result = (new MySqlCastRenderer())->renderNullCast($type);
        self::assertSame('CAST(NULL AS DATETIME)', $result);
    }

    public function testRenderNullCastTimestamp(): void
    {
        $type = new ColumnType(ColumnTypeFamily::TIMESTAMP, 'TIMESTAMP');
        $result = (new MySqlCastRenderer())->renderNullCast($type);
        self::assertSame('CAST(NULL AS DATETIME)', $result);
    }

    public function testRenderNullCastTime(): void
    {
        $type = new ColumnType(ColumnTypeFamily::TIME, 'TIME');
        $result = (new MySqlCastRenderer())->renderNullCast($type);
        self::assertSame('CAST(NULL AS TIME)', $result);
    }

    public function testRenderNullCastBinary(): void
    {
        $type = new ColumnType(ColumnTypeFamily::BINARY, 'BINARY');
        $result = (new MySqlCastRenderer())->renderNullCast($type);
        self::assertSame('CAST(NULL AS BINARY)', $result);
    }

    public function testRenderNullCastText(): void
    {
        $type = new ColumnType(ColumnTypeFamily::TEXT, 'TEXT');
        $result = (new MySqlCastRenderer())->renderNullCast($type);
        self::assertSame('CAST(NULL AS CHAR)', $result);
    }

    public function testRenderCastDecimalWithLowercaseNativeType(): void
    {
        $type = new ColumnType(ColumnTypeFamily::DECIMAL, 'decimal(10,2)');
        $result = (new MySqlCastRenderer())->renderCast("'1'", $type);
        self::assertSame("CAST('1' AS DECIMAL(10,2))", $result);
    }

    public function testRenderCastUnknownFamilyWithLowercaseNativeType(): void
    {
        $type = new ColumnType(ColumnTypeFamily::UNKNOWN, 'int');
        $result = (new MySqlCastRenderer())->renderCast("'1'", $type);
        self::assertSame("CAST('1' AS SIGNED)", $result);
    }

    public function testRenderCastUnknownFamilyWithLowercaseNativeTypeWithParams(): void
    {
        $type = new ColumnType(ColumnTypeFamily::UNKNOWN, 'int(11)');
        $result = (new MySqlCastRenderer())->renderCast("'1'", $type);
        self::assertSame("CAST('1' AS SIGNED)", $result);
    }

    #[DataProvider('providerFamilyWithExpectedCastType')]
    public function testEachFamilyProducesDistinctCastType(ColumnTypeFamily $family, string $expectedType): void
    {
        $type = new ColumnType($family, 'NATIVE');
        $result = (new MySqlCastRenderer())->renderCast("'x'", $type);
        self::assertSame("CAST('x' AS $expectedType)", $result, "Family {$family->value} did not produce expected cast type");
    }

    /**
     * @return \Generator<string, array{ColumnTypeFamily}>
     */
    public static function providerAllFamilies(): \Generator
    {
        foreach (ColumnTypeFamily::cases() as $family) {
            yield $family->value => [$family];
        }
    }

    /**
     * @return \Generator<string, array{ColumnTypeFamily, string}>
     */
    public static function providerFamilyWithExpectedCastType(): \Generator
    {
        yield 'INTEGER' => [ColumnTypeFamily::INTEGER, 'SIGNED'];
        yield 'FLOAT' => [ColumnTypeFamily::FLOAT, 'FLOAT'];
        yield 'DOUBLE' => [ColumnTypeFamily::DOUBLE, 'DOUBLE'];
        yield 'BOOLEAN' => [ColumnTypeFamily::BOOLEAN, 'UNSIGNED'];
        yield 'DATE' => [ColumnTypeFamily::DATE, 'DATE'];
        yield 'DATETIME' => [ColumnTypeFamily::DATETIME, 'DATETIME'];
        yield 'TIME' => [ColumnTypeFamily::TIME, 'TIME'];
        yield 'JSON' => [ColumnTypeFamily::JSON, 'JSON'];
        yield 'BINARY' => [ColumnTypeFamily::BINARY, 'BINARY'];
        yield 'STRING' => [ColumnTypeFamily::STRING, 'CHAR'];
    }
}
