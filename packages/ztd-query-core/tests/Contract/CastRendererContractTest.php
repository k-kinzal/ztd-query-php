<?php

declare(strict_types=1);

namespace Tests\Contract;

use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\CastRenderer;
use ZtdQuery\Schema\ColumnType;
use ZtdQuery\Schema\ColumnTypeFamily;

/**
 * Abstract contract test for CastRenderer implementations.
 *
 * Enforces contracts defined in quality-standards.md Section 1.7 and properties P-CR-1 through P-CR-5.
 */
abstract class CastRendererContractTest extends TestCase
{
    abstract protected function createRenderer(): CastRenderer;

    /**
     * renderCast must return a non-empty string for every type family (P-CR-1).
     */
    public function testRenderCastReturnsNonEmptyString(): void
    {
        $renderer = $this->createRenderer();

        foreach (ColumnTypeFamily::cases() as $family) {
            $type = new ColumnType($family, $this->nativeTypeFor($family));
            $result = $renderer->renderCast("'test'", $type);

            self::assertNotEmpty(
                $result,
                sprintf('renderCast returned empty string for family %s', $family->value)
            );
        }
    }

    /**
     * renderNullCast must return a non-empty string for every type family (P-CR-1).
     */
    public function testRenderNullCastReturnsNonEmptyString(): void
    {
        $renderer = $this->createRenderer();

        foreach (ColumnTypeFamily::cases() as $family) {
            $type = new ColumnType($family, $this->nativeTypeFor($family));
            $result = $renderer->renderNullCast($type);

            self::assertNotEmpty(
                $result,
                sprintf('renderNullCast returned empty string for family %s', $family->value)
            );
        }
    }

    /**
     * renderNullCast output must contain the CAST keyword (P-CR-2).
     */
    public function testRenderNullCastContainsCastKeyword(): void
    {
        $renderer = $this->createRenderer();

        foreach (ColumnTypeFamily::cases() as $family) {
            $type = new ColumnType($family, $this->nativeTypeFor($family));
            $result = $renderer->renderNullCast($type);

            self::assertStringContainsString(
                'CAST(',
                strtoupper($result),
                sprintf('renderNullCast output for family %s does not contain CAST(', $family->value)
            );
        }
    }

    /**
     * renderNullCast output must contain NULL.
     */
    public function testRenderNullCastContainsNullKeyword(): void
    {
        $renderer = $this->createRenderer();

        foreach (ColumnTypeFamily::cases() as $family) {
            $type = new ColumnType($family, $this->nativeTypeFor($family));
            $result = $renderer->renderNullCast($type);

            self::assertStringContainsString(
                'NULL',
                strtoupper($result),
                sprintf('renderNullCast output for family %s does not contain NULL', $family->value)
            );
        }
    }

    /**
     * All ColumnTypeFamily cases must be handled without error (P-CR-5).
     */
    public function testAllColumnTypeFamiliesHandled(): void
    {
        $renderer = $this->createRenderer();

        foreach (ColumnTypeFamily::cases() as $family) {
            $type = new ColumnType($family, $this->nativeTypeFor($family));

            $castResult = $renderer->renderCast("'value'", $type);
            $nullResult = $renderer->renderNullCast($type);

            self::assertNotEmpty(
                $castResult,
                sprintf('renderCast returned empty for family %s', $family->value)
            );
            self::assertNotEmpty(
                $nullResult,
                sprintf('renderNullCast returned empty for family %s', $family->value)
            );
        }
    }

    /**
     * renderCast for INTEGER must produce exact CAST(expression AS ...) form (P-CR-3).
     */
    public function testRenderCastForIntegerProducesExactForm(): void
    {
        $renderer = $this->createRenderer();
        $type = new ColumnType(ColumnTypeFamily::INTEGER, $this->nativeTypeFor(ColumnTypeFamily::INTEGER));

        $result = $renderer->renderCast("'42'", $type);

        self::assertMatchesRegularExpression(
            '/^CAST\(\'42\'\s+AS\s+\S+\)$/i',
            $result,
            'renderCast for INTEGER must produce CAST(expression AS type) format'
        );
    }

    /**
     * renderNullCast must produce exact CAST(NULL AS type) form.
     */
    public function testRenderNullCastProducesExactForm(): void
    {
        $renderer = $this->createRenderer();
        $type = new ColumnType(ColumnTypeFamily::INTEGER, $this->nativeTypeFor(ColumnTypeFamily::INTEGER));

        $result = $renderer->renderNullCast($type);

        self::assertMatchesRegularExpression(
            '/^CAST\(NULL\s+AS\s+\S+\)$/i',
            $result,
            'renderNullCast must produce CAST(NULL AS type) format'
        );
    }

    /**
     * renderCast for STRING must include VARCHAR or TEXT or CHAR type.
     */
    public function testRenderCastForStringIncludesStringType(): void
    {
        $renderer = $this->createRenderer();
        $type = new ColumnType(ColumnTypeFamily::STRING, $this->nativeTypeFor(ColumnTypeFamily::STRING));

        $result = $renderer->renderCast("'hello'", $type);
        $upper = strtoupper($result);

        self::assertTrue(
            str_contains($upper, 'VARCHAR') || str_contains($upper, 'TEXT') || str_contains($upper, 'CHAR'),
            sprintf('renderCast for STRING must include VARCHAR, TEXT, or CHAR, got: %s', $result)
        );
    }

    /**
     * renderCast must be deterministic: same inputs produce identical outputs (P-CR-4).
     */
    public function testRenderCastIsDeterministic(): void
    {
        $renderer = $this->createRenderer();
        $type = new ColumnType(ColumnTypeFamily::INTEGER, $this->nativeTypeFor(ColumnTypeFamily::INTEGER));

        $result1 = $renderer->renderCast('42', $type);
        $result2 = $renderer->renderCast('42', $type);

        self::assertSame($result1, $result2);

        $nullResult1 = $renderer->renderNullCast($type);
        $nullResult2 = $renderer->renderNullCast($type);

        self::assertSame($nullResult1, $nullResult2);
    }

    /**
     * Provide a representative native type string for a given family.
     * Subclasses may override this to provide platform-specific native types.
     */
    protected function nativeTypeFor(ColumnTypeFamily $family): string
    {
        return match ($family) {
            ColumnTypeFamily::INTEGER => 'INTEGER',
            ColumnTypeFamily::FLOAT => 'FLOAT',
            ColumnTypeFamily::DOUBLE => 'DOUBLE',
            ColumnTypeFamily::DECIMAL => 'DECIMAL(10,2)',
            ColumnTypeFamily::STRING => 'VARCHAR(255)',
            ColumnTypeFamily::TEXT => 'TEXT',
            ColumnTypeFamily::BOOLEAN => 'BOOLEAN',
            ColumnTypeFamily::DATE => 'DATE',
            ColumnTypeFamily::TIME => 'TIME',
            ColumnTypeFamily::DATETIME => 'DATETIME',
            ColumnTypeFamily::TIMESTAMP => 'TIMESTAMP',
            ColumnTypeFamily::BINARY => 'BLOB',
            ColumnTypeFamily::JSON => 'JSON',
            ColumnTypeFamily::UNKNOWN => 'CUSTOM_TYPE',
        };
    }
}
