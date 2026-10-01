<?php

declare(strict_types=1);

namespace Tests\Unit\Semantic\Schema;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Dialect;
use Tests\Scenario\SemanticCases;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Semantic\Schema\Table::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class TableTest extends TestCase
{
    public function testColumnPreservesDeclarationIdentity(): void
    {
        $table = SemanticCases::table(Dialect::Sqlite);
        self::assertSame($table->columns[0], $table->column('FOO'));
        self::assertNull($table->column('missing'));
    }
}
