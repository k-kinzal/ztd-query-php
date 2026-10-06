<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Lowering\Table\AlterTableRule::class)]
#[Medium]
final class AlterTableRuleTest extends TestCase
{
    public function testStatementLowersAMoveAll(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('ALTER INDEX ALL IN TABLESPACE a SET TABLESPACE b');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\AlterTableRule($lowering))->statement($tree->find('AlterTableStmt')[0]);
        self::assertSame('SqlSemantics\\Platform\\PostgreSql\\Statement\\Table\\Alter\\MoveAll', get_debug_type($value));
    }

    public function testCommandsLowersEveryAction(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('ALTER TABLE t SET LOGGED, SET UNLOGGED');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\AlterTableRule($lowering))->commands($tree->find('alter_table_cmds')[0]);
        self::assertSame(2, count($value));
    }

    public function testPartitionLowersADetach(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('ALTER TABLE t DETACH PARTITION p FINALIZE');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\AlterTableRule($lowering))->partition($tree->find('partition_cmd')[0]);
        $n1 = $value;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Partition\DetachPartition::class, $n1);
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Table\Partition\DetachMode::Finalize, $n1->mode);
    }
}
