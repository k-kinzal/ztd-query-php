<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Utility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\PostgreSql\PostgreSqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Maintenance\Checkpoint;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\SetParameter;
use SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\SetTimeZone;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Lowering\Utility\UtilityCommands::class)]
#[Medium]
final class UtilityCommandsTest extends TestCase
{
    public function testStatementHandsEachStatementToItsGroup(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        self::assertSame(
            ['BEGIN', 'SET a TO 1', 'VACUUM', 'LISTEN c'],
            [$semantics->analyze('BEGIN')->toString(), $semantics->analyze('SET a = 1')->toString(), $semantics->analyze('VACUUM')->toString(), $semantics->analyze('LISTEN c')->toString()],
        );
    }

    public function testStatementLowersANodeOfTheFamily(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('CHECKPOINT');
        self::assertInstanceOf(Checkpoint::class, $lowering->utilities->statement($tree->find('CheckPointStmt')[0]));
    }

    public function testStatementRefusesANodeOfAnotherFamily(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT 1');
        $this->expectExceptionMessage('No semantic rule is implemented for: SelectStmt: select_no_parens');
        $lowering->utilities->statement($tree->find('SelectStmt')[0]);
    }

    public function testSetResetLowersTheClauseAsTheCommand(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        self::assertInstanceOf(SetParameter::class, $lowering->utilities->setReset((new PostgreSqlParser('pg-17.2'))->parse('ALTER DATABASE d SET x TO 1')->find('SetResetClause')[0]));
        self::assertInstanceOf(SetTimeZone::class, $lowering->utilities->setReset((new PostgreSqlParser('pg-17.2'))->parse('ALTER FUNCTION f() SET TIME ZONE UTC')->find('FunctionSetResetClause')[0]));
    }
}
