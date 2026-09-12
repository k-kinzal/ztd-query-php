<?php

declare(strict_types=1);

namespace Tests\Unit\Platform;

use Override;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\Contract\CastRendererContractTest;
use Tests\Fake\FakeCastRenderer;
use ZtdQuery\Platform\CastRenderer;
use ZtdQuery\Schema\ColumnDeclaration;
use ZtdQuery\Schema\ColumnTypeFamily;

#[CoversNothing]
final class CastRendererTest extends CastRendererContractTest
{
    public function createRenderer(): CastRenderer
    {
        return new FakeCastRenderer();
    }

    #[Override]
    public function nativeTypeFor(ColumnTypeFamily $family): string
    {
        return match ($family) {
            ColumnTypeFamily::INTEGER => 'INTEGER',
            ColumnTypeFamily::FLOAT => 'REAL',
            ColumnTypeFamily::DOUBLE => 'REAL',
            ColumnTypeFamily::DECIMAL => 'NUMERIC(10,2)',
            ColumnTypeFamily::STRING => 'TEXT',
            ColumnTypeFamily::TEXT => 'TEXT',
            ColumnTypeFamily::BOOLEAN => 'INTEGER',
            ColumnTypeFamily::DATE => 'TEXT',
            ColumnTypeFamily::TIME => 'TEXT',
            ColumnTypeFamily::DATETIME => 'TEXT',
            ColumnTypeFamily::TIMESTAMP => 'TEXT',
            ColumnTypeFamily::BINARY => 'BLOB',
            ColumnTypeFamily::JSON => 'TEXT',
            ColumnTypeFamily::UNKNOWN => 'CUSTOM_TYPE',
        };
    }

    public function testRenderCastWritesTheExpressionAsTheTypeItIsBeingReadFor(): void
    {
        $renderer = $this->createRenderer();
        $type = new ColumnDeclaration(ColumnTypeFamily::INTEGER, 'INTEGER');

        self::assertStringContainsString('1', $renderer->renderCast('1', $type));
    }

    public function testRenderNullCastWritesANullTheServerWillReadAsThatType(): void
    {
        $renderer = $this->createRenderer();
        $type = new ColumnDeclaration(ColumnTypeFamily::TEXT, 'TEXT');

        self::assertStringContainsStringIgnoringCase('null', $renderer->renderNullCast($type));
    }
}
