<?php

declare(strict_types=1);

namespace Tests\Unit\Platform;

use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\Contract\CastRendererContractTest;
use Tests\Fake\FakeCastRenderer;
use ZtdQuery\Platform\CastRenderer;
use ZtdQuery\Schema\ColumnTypeFamily;

#[CoversNothing]
final class CastRendererTest extends CastRendererContractTest
{
    protected function createRenderer(): CastRenderer
    {
        return new FakeCastRenderer();
    }

    #[\Override]
    protected function nativeTypeFor(ColumnTypeFamily $family): string
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
}
