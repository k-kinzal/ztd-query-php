<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Access;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\PostgreSql\PostgreSqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\PostgreSql\Lowering\Access\Privileges;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Platform;

#[CoversClass(Privileges::class)]
#[Small]
final class PrivilegesTest extends TestCase
{
    public function testStatementIsAnImplementationGapUntilTheFamilyImplementsIt(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('GRANT SELECT ON t TO u');
        $this->expectExceptionMessage('No semantic rule is implemented for: GrantStmt: GRANT privileges ON privilege_target TO grantee_list');
        $lowering->privileges->statement($tree->find('GrantStmt')[0]);
    }
}
