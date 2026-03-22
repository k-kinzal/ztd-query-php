<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlFaker\Sqlite\SnapshotCompiler;

#[CoversNothing]
final class SnapshotCompilerTest extends TestCase
{
    public function testCompileBuildsAContractGrammarFromLemonSource(): void
    {
        $source = <<<'LEMON'
cmd ::= SELECT expr.
expr ::= INTEGER.
LEMON;

        $grammar = (new SnapshotCompiler())->compile($source);

        self::assertSame('cmd', $grammar->startSymbol);
        self::assertSame(['t:SELECT', 'nt:expr'], $grammar->rule('cmd')?->alternatives[0]->sequence());
    }
}
