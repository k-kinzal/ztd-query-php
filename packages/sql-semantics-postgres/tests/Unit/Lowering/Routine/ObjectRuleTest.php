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
use SqlSemantics\Platform\PostgreSql\Lowering\Routine\ObjectRule;
use SqlSemantics\Platform\PostgreSql\Platform;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\CastPair;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\MemberName;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\OperatorGroupName;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\RelationTarget;

#[CoversClass(ObjectRule::class)]
#[Small]
final class ObjectRuleTest extends TestCase
{
    public function testKindLowersEachKindNonterminal(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('DROP PROCEDURAL LANGUAGE l; DROP MATERIALIZED VIEW v; DROP RULE r ON t; COMMENT ON TABLESPACE s IS NULL');
        $rule = new ObjectRule($lowering);
        self::assertSame([ObjectKind::Language, ObjectKind::MaterializedView, ObjectKind::Rule, ObjectKind::Tablespace], [$rule->kind($tree->find('drop_type_name')[0]), $rule->kind($tree->find('object_type_any_name')[0]), $rule->kind($tree->find('object_type_name_on_any_name')[0]), $rule->kind($tree->find('object_type_name')[0])]);
    }

    public function testKindRejectsAnotherNonterminal(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('DROP TABLE t');
        $this->expectExceptionMessage('No semantic rule is implemented for: any_name: ColId');
        (new ObjectRule($lowering))->kind($tree->find('any_name')[0]);
    }

    public function testTokenTellsTheTerminalAtAPosition(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('DROP TABLE IF EXISTS t');
        $form = $lowering->productions->form($tree->find('DropStmt')[0]);
        self::assertSame([true, false], [(new ObjectRule($lowering))->token($form, 2, 'IF_P'), (new ObjectRule($lowering))->token($form, 1, 'IF_P')]);
    }

    public function testHasTellsWhetherATerminalIsWritten(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('DROP TABLE IF EXISTS t');
        $form = $lowering->productions->form($tree->find('DropStmt')[0]);
        self::assertSame([true, false], [(new ObjectRule($lowering))->has($form, 'EXISTS'), (new ObjectRule($lowering))->has($form, 'CASCADE')]);
    }

    public function testStartSkipsProcedural(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('ALTER PROCEDURAL LANGUAGE l OWNER TO r');
        self::assertSame(3, (new ObjectRule($lowering))->start($lowering->productions->form($tree->find('AlterOwnerStmt')[0])));
    }

    public function testStartRejectsAProductionWithoutObject(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('CHECKPOINT');
        $this->expectExceptionMessage('No semantic rule is implemented for: CheckPointStmt: CHECKPOINT');
        (new ObjectRule($lowering))->start($lowering->productions->form($tree->find('CheckPointStmt')[0]));
    }

    public function testNamedLowersAMemberOfATable(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('COMMENT ON POLICY p ON s.t IS NULL');
        [$kind, $object] = (new ObjectRule($lowering))->named($lowering->productions->form($tree->find('CommentStmt')[0]), 2);
        self::assertSame(ObjectKind::Policy, $kind);
        self::assertInstanceOf(MemberName::class, $object);
    }

    public function testKeyedLowersACast(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('COMMENT ON CAST (int AS text) IS NULL');
        [$kind, $object] = (new ObjectRule($lowering))->keyed($lowering->productions->form($tree->find('CommentStmt')[0]), 2);
        self::assertSame(ObjectKind::Cast, $kind);
        self::assertInstanceOf(CastPair::class, $object);
    }

    public function testOperatorLowersAnOperatorFamily(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('COMMENT ON OPERATOR FAMILY f USING btree IS NULL');
        [$kind, $object] = (new ObjectRule($lowering))->operator($lowering->productions->form($tree->find('CommentStmt')[0]), 2);
        self::assertSame(ObjectKind::OperatorFamily, $kind);
        self::assertInstanceOf(OperatorGroupName::class, $object);
    }

    public function testAlteredLowersARelationAndTheNextPosition(): void
    {
        $lowering = new Lowering((new Platform())->productions(new LanguageProfile(GrammarRelease::PostgreSql172)), new Leaves(), GrammarRelease::PostgreSql172);
        $tree = (new PostgreSqlParser('pg-17.2'))->parse('ALTER TABLE ONLY t RENAME TO u');
        [$object, $next] = (new ObjectRule($lowering))->altered($lowering->productions->form($tree->find('RenameStmt')[0]), 2);
        self::assertInstanceOf(RelationTarget::class, $object);
        self::assertSame([true, 3], [$object->relation->only, $next]);
    }
}
