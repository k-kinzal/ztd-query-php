<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlFaker\Sqlite\SnapshotLoader;

#[CoversNothing]
final class SnapshotLoaderTest extends TestCase
{
    public function testSnapshotLoaderProjectsConfiguredSqliteSnapshotIntoContractGrammar(): void
    {
        $snapshot = (new SnapshotLoader())->load();

        self::assertNotSame('', $snapshot->startSymbol);
        self::assertNotNull($snapshot->rule('selectnowith'));
    }
}
