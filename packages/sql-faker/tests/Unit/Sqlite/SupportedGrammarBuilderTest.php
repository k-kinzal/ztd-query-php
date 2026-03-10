<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlFaker\Sqlite\SnapshotLoader;
use SqlFaker\Sqlite\SupportedGrammarBuilder;

#[CoversNothing]
final class SupportedGrammarBuilderTest extends TestCase
{
    public function testSupportedGrammarBuilderProjectsRewrittenSqliteGrammarIntoContractGrammar(): void
    {
        $snapshot = (new SnapshotLoader())->load();
        $supportedGrammar = (new SupportedGrammarBuilder())->build($snapshot);

        self::assertSame($snapshot->startSymbol, $supportedGrammar->startSymbol);
        self::assertNotNull($supportedGrammar->rule('selectnowith'));
    }
}
