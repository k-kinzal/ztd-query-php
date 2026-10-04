<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\PostgreSql\PostgreSqlParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\PostgreSql\Lowering\Lowering;
use SqlSemantics\Platform\PostgreSql\Lowering\Routine\OperatorClassRule;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Statement\Operator\CreateOperatorClass;
use SqlSemantics\Platform\PostgreSql\Statement\Operator\FunctionMember;
use SqlSemantics\Platform\PostgreSql\Statement\Operator\MemberKind;
use SqlSemantics\Platform\PostgreSql\Statement\Operator\OperatorFamilyRemoval;
use SqlSemantics\Platform\PostgreSql\Statement\Option\KeywordWord;

#[CoversClass(OperatorClassRule::class)]
#[Small]
final class OperatorClassRuleTest extends TestCase
{
    public function testStatementLowersClassesAndFamilies(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('CREATE OPERATOR CLASS c FOR TYPE int USING btree AS STORAGE int; ALTER OPERATOR FAMILY f USING btree DROP OPERATOR 1 (int)');
        $rule = new OperatorClassRule($lowering);
        self::assertSame([CreateOperatorClass::class, OperatorFamilyRemoval::class], [$rule->statement($tree->find('CreateOpClassStmt')[0])::class, $rule->statement($tree->find('AlterOpFamilyStmt')[0])::class]);
    }

    public function testItemsLowersTheList(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('CREATE OPERATOR CLASS c FOR TYPE int USING btree AS OPERATOR 1 <, FUNCTION 1 f');
        self::assertCount(2, (new OperatorClassRule($lowering))->items($tree->find('opclass_item_list')[0]));
    }

    public function testItemLowersOperandTypesOfAFunction(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('CREATE OPERATOR CLASS c FOR TYPE int USING btree AS FUNCTION 1 (int, int) f(int, int)');
        $item = (new OperatorClassRule($lowering))->item($tree->find('opclass_item')[0]);
        self::assertInstanceOf(FunctionMember::class, $item);
        self::assertCount(2, $item->operandTypes ?? []);
    }

    public function testPurposeLowersTheSortFamily(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('CREATE OPERATOR CLASS c FOR TYPE int USING gist AS OPERATOR 1 <-> FOR ORDER BY f RECHECK');
        self::assertSame('f', (new OperatorClassRule($lowering))->purpose($tree->find('opclass_purpose')[0], $tree->find('opt_recheck')[0])?->sortFamily?->last()->value);
    }

    public function testFamilyLowersTheFamily(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('CREATE OPERATOR CLASS c FOR TYPE int USING btree FAMILY s.f AS STORAGE int');
        self::assertSame('f', (new OperatorClassRule($lowering))->family($tree->find('opt_opfamily')[0])?->last()->value);
    }

    public function testRemovalsLowersTheKinds(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('ALTER OPERATOR FAMILY f USING btree DROP OPERATOR 1 (int), FUNCTION 2 (int)');
        $removals = (new OperatorClassRule($lowering))->removals($tree->find('opclass_drop_list')[0]);
        self::assertSame([MemberKind::Operator, MemberKind::Function], [$removals[0]->kind, $removals[1]->kind]);
    }

    public function testDefinitionsLowersAnAttributeWithoutValue(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('ALTER OPERATOR = (int, int) SET (hashes, restrict = NONE)');
        $definitions = (new OperatorClassRule($lowering))->definitions($tree->find('operator_def_list')[0]);
        self::assertNull($definitions[0]->argument);
        self::assertInstanceOf(KeywordWord::class, $definitions[1]->argument);
    }

    public function testArgumentLowersAReservedKeyword(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('ALTER OPERATOR = (int, int) SET (restrict = ALL)');
        self::assertEquals(new KeywordWord(new \SqlSemantics\Statement\Identifier\Name('all')), (new OperatorClassRule($lowering))->argument($tree->find('operator_def_arg')[0]));
    }
}
