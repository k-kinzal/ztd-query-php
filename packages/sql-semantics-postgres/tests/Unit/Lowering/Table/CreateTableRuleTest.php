<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Lowering\Table\CreateTableRule::class)]
#[Medium]
final class CreateTableRuleTest extends TestCase
{
    public function testStatementLowersATypedTable(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE TABLE IF NOT EXISTS t OF ty');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\CreateTableRule($lowering))->statement($tree->find('CreateStmt')[0]);
        $n1 = $value->definition;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Element\TypedTable::class, $n1);
        self::assertSame([
          0 => true,
          1 => 'SqlSemantics\\Platform\\PostgreSql\\Statement\\Table\\Element\\TypedTable',
        ], [$value->ifNotExists, $n1::class]);
    }

    public function testForeignLowersAForeignPartition(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE FOREIGN TABLE f PARTITION OF p DEFAULT SERVER s');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\CreateTableRule($lowering))->foreign($tree->find('CreateForeignTableStmt')[0]);
        $n1 = $value->definition;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Element\PartitionOf::class, $n1);
        self::assertSame('SqlSemantics\\Platform\\PostgreSql\\Statement\\Table\\Element\\PartitionOf', $n1::class);
    }

    public function testFormLowersAColumnList(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE TABLE t (a int)');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\CreateTableRule($lowering))->form($lowering->productions->form($tree->find('CreateStmt')[0]), 3, 'list');
        $n1 = $value;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Element\ListedColumns::class, $n1);
        self::assertSame(1, count($n1->elements()));
    }

    public function testPersistenceLowersTheSpelling(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE GLOBAL TEMP TABLE t (a int)');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\CreateTableRule($lowering))->persistence($tree->find('OptTemp')[0]);
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Table\Persistence::GlobalTemp, $value);
    }

    public function testParentsLowersInherits(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE TABLE t () INHERITS (a, b)');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\CreateTableRule($lowering))->parents($tree->find('OptInherit')[0]);
        self::assertSame(2, count($value));
    }

    public function testMethodLowersUsing(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE TABLE t () USING heap');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\CreateTableRule($lowering))->method($tree->find('table_access_method_clause')[0]);
        $n1 = $value;
        self::assertInstanceOf(\SqlSemantics\Statement\Identifier\Name::class, $n1);
        self::assertSame('heap', $n1->value);
    }

    public function testStorageIsEmptyForWithoutOids(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE TABLE t () WITHOUT OIDS');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\CreateTableRule($lowering))->storage($tree->find('OptWith')[0]);
        self::assertSame([
        ], $value);
    }

    public function testOnCommitLowersTheAction(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE TEMP TABLE t () ON COMMIT DROP');
        $value = (new \SqlSemantics\Platform\PostgreSql\Lowering\Table\CreateTableRule($lowering))->onCommit($tree->find('OnCommitOption')[0]);
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Table\OnCommit::Drop, $value);
    }
}
