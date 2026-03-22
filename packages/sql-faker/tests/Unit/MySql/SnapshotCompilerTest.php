<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlFaker\MySql\SnapshotCompiler;

#[CoversNothing]
final class SnapshotCompilerTest extends TestCase
{
    public function testCompileBuildsAContractGrammarFromBisonSource(): void
    {
        $source = <<<'BISON'
%start stmt
%token TOKEN
%%
stmt: TOKEN;
BISON;

        $grammar = (new SnapshotCompiler())->compile($source);

        self::assertSame('stmt', $grammar->startSymbol);
        self::assertSame(['t:TOKEN'], $grammar->rule('stmt')?->alternatives[0]->sequence());
    }
}
