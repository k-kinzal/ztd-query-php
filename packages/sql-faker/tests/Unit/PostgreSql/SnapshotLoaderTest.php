<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlFaker\PostgreSql\SnapshotLoader;

#[CoversNothing]
final class SnapshotLoaderTest extends TestCase
{
    public function testSnapshotLoaderProjectsConfiguredPostgreSqlSnapshotIntoContractGrammar(): void
    {
        $snapshot = (new SnapshotLoader())->load();

        self::assertNotSame('', $snapshot->startSymbol);
        self::assertNotNull($snapshot->rule('SelectStmt'));
    }
}
