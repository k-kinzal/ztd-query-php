<?php

declare(strict_types=1);

namespace Tests\Unit\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Rewrite\InsertSelectProjection;

#[CoversClass(InsertSelectProjection::class)]
final class InsertSelectProjectionTest extends TestCase
{
    public function testRepresentsSourceExpressionGeneratedIdentityAndNullWithoutSqlRendering(): void
    {
        $source = InsertSelectProjection::source('name', 2);
        $firstSource = InsertSelectProjection::source('id', 0);
        $expression = InsertSelectProjection::defaultExpression('status', "'active'");
        $generatedIdentity = InsertSelectProjection::generatedIdentity('generated_id', 1);
        $null = InsertSelectProjection::nullValue('note');

        self::assertSame('name', $source->targetColumn());
        self::assertSame(2, $source->sourceIndex());
        self::assertNull($source->defaultExpressionValue());
        self::assertNull($source->generatedIdentityStart());
        self::assertFalse($source->isNullValue());
        self::assertSame(0, $firstSource->sourceIndex());
        self::assertSame("'active'", $expression->defaultExpressionValue());
        self::assertNull($expression->sourceIndex());
        self::assertNull($expression->generatedIdentityStart());
        self::assertFalse($expression->isNullValue());
        self::assertSame(1, $generatedIdentity->generatedIdentityStart());
        self::assertNull($generatedIdentity->sourceIndex());
        self::assertNull($generatedIdentity->defaultExpressionValue());
        self::assertFalse($generatedIdentity->isNullValue());
        self::assertTrue($null->isNullValue());
        self::assertNull($null->sourceIndex());
        self::assertNull($null->defaultExpressionValue());
        self::assertNull($null->generatedIdentityStart());
    }

    public function testRejectsNegativeSourceIndex(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Source index must not be negative.');

        InsertSelectProjection::source('name', -1);
    }

    public function testRejectsNonPositiveGeneratedIdentityStart(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Generated identity start must be positive.');

        InsertSelectProjection::generatedIdentity('id', 0);
    }
}
