<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\ForeignData;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\ForeignData\DropUserMapping::class)]
#[Medium]
final class DropUserMappingTest extends TestCase
{
    public function testRenderWritesIfExists(): void
    {
        self::assertSame('DROP USER MAPPING IF EXISTS FOR CURRENT_USER SERVER s', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('DROP USER MAPPING IF EXISTS FOR CURRENT_USER SERVER s')->toString());
    }

    public function testDeriveStatementRecordsNothing(): void
    {
        self::assertSame([], (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('DROP USER MAPPING FOR USER SERVER s')->facts->diagnostics);
    }
}
