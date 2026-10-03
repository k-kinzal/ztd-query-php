<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Manipulation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\PostgreSql\PostgreSqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Lowering\Manipulation\Manipulations;
use SqlSemantics\Platform\PostgreSql\Platform;

#[CoversClass(Manipulations::class)]
#[Small]
final class ManipulationsTest extends TestCase
{
    public function testStatementIsAnImplementationGapUntilTheFamilyImplementsIt(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('DELETE FROM t');
        $this->expectExceptionMessage('No semantic rule is implemented for: DeleteStmt: opt_with_clause DELETE_P FROM relation_expr_opt_alias using_clause where_or_current_clause returning_clause');
        $lowering->manipulations->statement($tree->find('DeleteStmt')[0]);
    }

    public function testPreparableIsAnImplementationGapUntilTheFamilyImplementsIt(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('PREPARE p AS SELECT 1');
        $this->expectExceptionMessage('No semantic rule is implemented for: PreparableStmt: SelectStmt');
        $lowering->manipulations->preparable($tree->find('PreparableStmt')[0]);
    }
}
