<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlFaker\MySql\SnapshotLoader;
use SqlFaker\MySql\SupportedGrammarBuilder;

#[CoversNothing]
final class SupportedGrammarBuilderTest extends TestCase
{
    public function testSupportedGrammarBuilderProjectsRewrittenMySqlGrammarIntoContractGrammar(): void
    {
        $snapshot = (new SnapshotLoader())->load();
        $supportedGrammar = (new SupportedGrammarBuilder())->build($snapshot);

        self::assertSame($snapshot->startSymbol, $supportedGrammar->startSymbol);
        self::assertNotNull($supportedGrammar->rule('rollback'));
        self::assertNotSame([], (new SupportedGrammarBuilder())->rewriteProgram()->stepIds());
    }
}
