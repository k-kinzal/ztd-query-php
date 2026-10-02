<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\PostgreSql\PostgreSqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select;

#[CoversClass(Lowering::class)]
#[Small]
final class LoweringTest extends TestCase
{
    public function testStatementsSkipsEmptyStatements(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse(';SELECT 1;;SELECT a FROM t;');
        $statements = $lowering->statements($tree);
        self::assertCount(2, $statements);
        self::assertInstanceOf(Select::class, $statements[1]);
    }

    public function testItemsFlattensAListInSourceOrder(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT 1 FROM t AS x (a, b, c)');
        $items = $lowering->items($tree->find('name_list')[0], 'name_list: name', 'name_list: name_list , name');
        self::assertSame(['a', 'b', 'c'], array_map(static fn (\SqlParser\Parser\Node $item): string => $item->tokens()[0]->text, $items));
    }

    public function testItemsReportsAListProductionTheRuleDoesNotName(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT 1 FROM t AS x (a, b)');
        $this->expectExceptionMessage('No semantic rule is implemented for: name_list: name_list , name');
        $lowering->items($tree->find('name_list')[0], 'name_list: name');
    }

    public function testOptionalIsNullForTheEmptyStatement(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse(';');
        self::assertNull($lowering->optional($tree->find('stmt')[0]));
    }

    public function testStatementRoutesAStatementToItsFamily(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('CREATE TABLE t (a int)');
        $this->expectExceptionMessage('No semantic rule is implemented for: CreateStmt:');
        $lowering->statement($tree->find('CreateStmt')[0]);
    }

    public function testStatementReportsANodeThatIsNotAStatement(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('SELECT 1');
        $this->expectExceptionMessage('No semantic rule is implemented for: statement target_el');
        $lowering->statement($tree->find('target_el')[0]);
    }
}
