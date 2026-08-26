<?php

declare(strict_types=1);

namespace Tests\Unit\Platform;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fake\FakeValueRenderer;
use ZtdQuery\Schema\ColumnType;
use ZtdQuery\Schema\ColumnTypeFamily;

#[CoversNothing]
final class ValueRendererTest extends TestCase
{
    public function testRenderValueWritesNullAsSqlSpellsIt(): void
    {
        self::assertSame('NULL', (new FakeValueRenderer())->renderValue(null));
    }

    public function testRenderValueWritesTextQuotedAndItsQuotesDoubled(): void
    {
        self::assertSame("'a''b'", (new FakeValueRenderer())->renderValue("a'b"));
    }

    public function testRenderValueWritesANumberWithoutQuotes(): void
    {
        self::assertSame('7', (new FakeValueRenderer())->renderValue(7));
    }

    public function testRenderValueTakesTheColumnTypeIntoAccountWhereOneIsKnown(): void
    {
        self::assertSame(
            "'7'",
            (new FakeValueRenderer())->renderValue(7, new ColumnType(ColumnTypeFamily::TEXT, 'text')),
        );
    }
}
