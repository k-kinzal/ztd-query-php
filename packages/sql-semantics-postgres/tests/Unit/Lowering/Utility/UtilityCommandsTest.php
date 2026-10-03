<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Utility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\PostgreSql\PostgreSqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Lowering\Utility\UtilityCommands;
use SqlSemantics\Platform\PostgreSql\Platform;

#[CoversClass(UtilityCommands::class)]
#[Small]
final class UtilityCommandsTest extends TestCase
{
    public function testStatementIsAnImplementationGapUntilTheFamilyImplementsIt(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('CHECKPOINT');
        $this->expectExceptionMessage('No semantic rule is implemented for: CheckPointStmt: CHECKPOINT');
        $lowering->utilities->statement($tree->find('CheckPointStmt')[0]);
    }

    public function testSetResetIsAnImplementationGapUntilTheFamilyImplementsIt(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('ALTER DATABASE d SET x TO 1');
        $this->expectExceptionMessage('No semantic rule is implemented for: SetResetClause: SET set_rest');
        $lowering->utilities->setReset($tree->find('SetResetClause')[0]);
    }
}
