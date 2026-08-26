<?php

declare(strict_types=1);

namespace Tests\Unit\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Exception\InvalidDefinitionException;
use ZtdQuery\Rewrite\InsertRowProjection;

#[CoversClass(InsertRowProjection::class)]
final class InsertRowProjectionTest extends TestCase
{
    public function testDefaultExpressionValueDefaultExpressionRepresentsProvidedDefaultGeneratedAndNullValuesWithoutSqlRendering(): void
    {
        $provided = InsertRowProjection::provided('name', "'Ada'");
        $default = InsertRowProjection::defaultExpression('status', "'active'");
        $generated = InsertRowProjection::generatedIdentity('id', 8);
        $null = InsertRowProjection::nullValue('note');

        self::assertSame('name', $provided->targetColumn());
        self::assertSame("'Ada'", $provided->providedExpression());
        self::assertNull($provided->defaultExpressionValue());
        self::assertNull($provided->generatedIdentityValue());
        self::assertFalse($provided->isNullValue());
        self::assertSame("'active'", $default->defaultExpressionValue());
        self::assertNull($default->providedExpression());
        self::assertNull($default->generatedIdentityValue());
        self::assertFalse($default->isNullValue());
        self::assertSame(8, $generated->generatedIdentityValue());
        self::assertNull($generated->providedExpression());
        self::assertNull($generated->defaultExpressionValue());
        self::assertFalse($generated->isNullValue());
        self::assertTrue($null->isNullValue());
        self::assertNull($null->providedExpression());
        self::assertNull($null->defaultExpressionValue());
        self::assertNull($null->generatedIdentityValue());
    }

    public function testRejectsNonPositiveGeneratedIdentityValue(): void
    {
        $this->expectException(InvalidDefinitionException::class);
        $this->expectExceptionMessage('Generated identity value must be positive.');

        InsertRowProjection::generatedIdentity('id', 0);
    }

    public function testGeneratedIdentityValueAcceptsMinimumGeneratedIdentityValue(): void
    {
        self::assertSame(1, InsertRowProjection::generatedIdentity('id', 1)->generatedIdentityValue());
    }

    public function testProvidedCarriesTheExpressionTheStatementWrote(): void
    {
        $projection = InsertRowProjection::provided('name', "'Ada'");

        self::assertSame("'Ada'", $projection->providedExpression());
        self::assertNull($projection->defaultExpressionValue());
    }

    public function testProvidedExpressionIsNothingForAColumnTheStatementLeftOut(): void
    {
        self::assertNull(InsertRowProjection::nullValue('note')->providedExpression());
    }

    public function testNullValueReadsBackAsNullAndNothingElse(): void
    {
        $projection = InsertRowProjection::nullValue('note');

        self::assertTrue($projection->isNullValue());
        self::assertNull($projection->providedExpression());
        self::assertNull($projection->generatedIdentityValue());
    }

    public function testIsNullValueIsFalseForEveryOtherKindOfColumn(): void
    {
        self::assertFalse(InsertRowProjection::provided('name', "'Ada'")->isNullValue());
        self::assertFalse(InsertRowProjection::generatedIdentity('id', 1)->isNullValue());
    }

    public function testTargetColumnNamesTheColumnOfTheTargetTable(): void
    {
        self::assertSame('name', InsertRowProjection::provided('name', "'Ada'")->targetColumn());
    }
}
