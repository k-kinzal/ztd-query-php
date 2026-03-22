<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlFaker\PostgreSql\SnapshotLoader;
use SqlFaker\PostgreSql\SupportedGrammarBuilder;

#[CoversNothing]
final class SupportedGrammarBuilderTest extends TestCase
{
    public function testSupportedGrammarBuilderProjectsRewrittenPostgreSqlGrammarIntoContractGrammar(): void
    {
        $snapshot = (new SnapshotLoader())->load();
        $supportedGrammar = (new SupportedGrammarBuilder())->build($snapshot);

        self::assertSame($snapshot->startSymbol, $supportedGrammar->startSymbol);
        self::assertNotNull($supportedGrammar->rule('SelectStmt'));
        self::assertNotSame([], (new SupportedGrammarBuilder())->rewriteProgram()->stepIds());
    }
}
