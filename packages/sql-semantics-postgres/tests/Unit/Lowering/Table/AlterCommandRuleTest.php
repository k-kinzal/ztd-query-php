<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Lowering\Table\AlterCommandRule::class)]
#[Medium]
final class AlterCommandRuleTest extends TestCase
{
    public function testCommandLowersANamedAction(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('ALTER TABLE t ENABLE ALWAYS TRIGGER g');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\AlterCommandRule($lowering))->command($tree->find('alter_table_cmd')[0]);
        $n1 = $value;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\NamedAction::class, $n1);
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\NamedActionKind::EnableAlwaysTrigger, $n1->kind);
    }

    public function testColumnLowersAStatisticsTargetByNumber(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('ALTER INDEX i ALTER 2 SET STATISTICS 10');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\AlterCommandRule($lowering))->column($lowering->productions->form($tree->find('alter_table_cmd')[0]));
        $n1 = $value;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Column\ColumnStatistics::class, $n1);
        $n2 = $n1->column;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant::class, $n2);
        self::assertSame('2', $n2->digits);
    }

    public function testRelationLowersADroppedColumn(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('ALTER TABLE t DROP IF EXISTS a CASCADE');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\AlterCommandRule($lowering))->relation($lowering->productions->form($tree->find('alter_table_cmd')[0]));
        $n1 = $value;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Column\DropColumn::class, $n1);
        $n2 = $value;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Column\DropColumn::class, $n2);
        self::assertSame([
          0 => true,
          1 =>
          \SqlSemantics\Platform\PostgreSql\Statement\Option\DropBehavior::Cascade,
        ], [$n1->ifExists, $n2->behavior]);
    }

    public function testSettingLowersReplicaIdentity(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('ALTER TABLE t REPLICA IDENTITY FULL');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\AlterCommandRule($lowering))->setting($lowering->productions->form($tree->find('alter_table_cmd')[0]));
        $n1 = $value;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Option\ReplicaIdentity::class, $n1);
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Option\ReplicaIdentityKind::Full, $n1->kind);
    }

    public function testDefaultedIsADropDefaultAction(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('ALTER TABLE t ALTER a DROP DEFAULT');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\AlterCommandRule($lowering))->defaulted(new \SqlSemantics\Statement\Identifier\Name('a'), $tree->find('alter_column_default')[0]);
        $n1 = $value;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Column\ColumnAction::class, $n1);
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Column\ColumnActionKind::DropDefault, $n1->kind);
    }

    public function testDefaultValueLowersTheExpression(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('ALTER TABLE t ALTER a SET DEFAULT 1');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\AlterCommandRule($lowering))->defaultValue($tree->find('alter_column_default')[0]);
        self::assertSame('SqlSemantics\\Platform\\PostgreSql\\Statement\\Literal\\Constant', get_debug_type($value));
    }

    public function testUsingIsNullWhenNotWritten(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('ALTER TABLE t ALTER a TYPE int');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\AlterCommandRule($lowering))->using($tree->find('alter_using')[0]);
        self::assertSame(null, $value);
    }

    public function testStatisticsIsNullForDefault(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('ALTER TABLE t ALTER a SET STATISTICS DEFAULT');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\AlterCommandRule($lowering))->statistics($tree->find('set_statistics_value')[0]);
        self::assertSame(null, $value);
    }

    public function testMethodIsNullForDefault(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('ALTER TABLE t SET ACCESS METHOD DEFAULT');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\AlterCommandRule($lowering))->method($tree->find('set_access_method_name')[0]);
        self::assertSame(null, $value);
    }

    public function testReplicaLowersUsingIndex(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('ALTER TABLE t REPLICA IDENTITY USING INDEX k');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\AlterCommandRule($lowering))->replica($tree->find('replica_identity')[0]);
        self::assertSame('k', $value->index?->value);
    }

    public function testIdentityKeepsTheOrder(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('ALTER TABLE t ALTER a SET CYCLE RESTART SET GENERATED ALWAYS');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\AlterCommandRule($lowering))->identity($tree->find('alter_identity_column_option_list')[0]);
        self::assertSame([
          0 => 'SqlSemantics\\Platform\\PostgreSql\\Statement\\Table\\Alter\\Column\\IdentitySetting',
          1 => 'SqlSemantics\\Platform\\PostgreSql\\Statement\\Table\\Alter\\Column\\IdentityRestart',
          2 => 'SqlSemantics\\Platform\\PostgreSql\\Statement\\Table\\Alter\\Column\\IdentityGeneration',
        ], array_map(static fn ($change): string => $change::class, $value));
    }
}
