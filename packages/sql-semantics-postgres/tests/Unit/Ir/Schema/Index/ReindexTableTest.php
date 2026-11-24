<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\IndexRebuildMode;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\ReindexTable;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\ReindexTableTarget;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;

#[CoversClass(ReindexTable::class)]
#[Small]
final class ReindexTableTest extends TestCase
{
    public function testToStringKeepsATemporaryOwnerAndItsRequestedMode(): void
    {
        $target = new ReindexTableTarget(new Identifier('pg_temp'), new Identifier('orders'));
        $value = new ReindexTable($target, IndexRebuildMode::Concurrent);

        self::assertSame('REINDEX TABLE CONCURRENTLY "pg_temp"."orders"', $value->toString());
    }

    public function testProfileIdUsesTheSelectedGrammar(): void
    {
        $target = new ReindexTableTarget(new Identifier('public'), new Identifier('orders'));
        $value = new ReindexTable($target, release: GrammarRelease::PostgreSql166);

        self::assertSame('pg-16.6/utf8/standard-conforming-strings/native', $value->profileId());
    }
}
