<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlFaker\MySql\SnapshotLoader;

#[CoversNothing]
final class SnapshotLoaderTest extends TestCase
{
    public function testSnapshotLoaderProjectsConfiguredMySqlSnapshotIntoContractGrammar(): void
    {
        $snapshot = (new SnapshotLoader('mysql-8.0.44'))->load();

        self::assertNotSame('', $snapshot->startSymbol);
        self::assertNotNull($snapshot->rule('rollback'));
    }
}
