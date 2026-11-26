<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Schema\DropTable;

/**
 * @covers \SqlSemantics\Platform\Sqlite\Ir\Schema\DropTable
 */
final class DropTableTest extends TestCase
{
    public function testProfileIdIdentifiesSqlite(): void
    {
        $statement = new DropTable(new Identifier('main'), new Identifier('records'));

        self::assertSame('sqlite-3.47.2/utf8/short-column-names/numbered-parameters', $statement->profileId());
    }

    public function testToStringQuotesTheAbsoluteTargetAndKeepsTheGuard(): void
    {
        $statement = new DropTable(new Identifier('main'), new Identifier('records "2026"'), true);

        self::assertSame('DROP TABLE IF EXISTS "main"."records ""2026"""', $statement->toString());
    }
}
