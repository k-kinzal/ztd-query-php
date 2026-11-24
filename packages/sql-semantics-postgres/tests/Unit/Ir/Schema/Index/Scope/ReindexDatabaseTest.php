<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index\Scope;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\IndexRebuildMode;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Scope\ReindexDatabase;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;

#[CoversClass(ReindexDatabase::class)]
#[Small]
final class ReindexDatabaseTest extends TestCase
{
    public function testToStringEmitsTheExactDatabaseName(): void
    {
        $rebuild = new ReindexDatabase(new Identifier('sales."app"'), IndexRebuildMode::Concurrent);

        self::assertSame('REINDEX DATABASE CONCURRENTLY "sales.""app"""', $rebuild->toString());
    }

    public function testProfileIdRetainsTheGrammar(): void
    {
        $rebuild = new ReindexDatabase(new Identifier('sales'), release: GrammarRelease::PostgreSql166);

        self::assertSame('pg-16.6/utf8/standard-conforming-strings/native', $rebuild->profileId());
    }
}
