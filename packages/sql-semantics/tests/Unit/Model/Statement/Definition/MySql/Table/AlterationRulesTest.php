<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\MySql\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\IndexLock;
use SqlSemantics\Model\Definition\MySqlTable\Table\TableCommand;
use SqlSemantics\Model\Definition\MySqlTable\TableAlgorithm;
use SqlSemantics\Model\Statement\Definition\MySql\Table\AlterationRules;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AlterationRules::class)]
#[Medium]
final class AlterationRulesTest extends TestCase
{
    public function testCombinationRejectsAPartitioningChangeBeforeAnotherAlteration(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t REMOVE PARTITIONING');
        $this->expectException(InvalidStructure::class);
        AlterationRules::combination($statement->origin, [TableCommand::RemovePartitioning, TableCommand::Force], TableAlgorithm::Default, IndexLock::Default);
    }

    public function testCombinationRejectsARequestWithAStandaloneCommandOnMySql56(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build('CREATE TABLE t(id INT)')))->bind('ALTER TABLE t DISCARD TABLESPACE');
        $this->expectException(InvalidStructure::class);
        AlterationRules::combination($statement->origin, [TableCommand::DiscardTablespace], TableAlgorithm::Copy, IndexLock::Default);
    }

    public function testStandaloneClassifiesPartitionCommands(): void
    {
        self::assertTrue(AlterationRules::standalone(TableCommand::ImportTablespace));
        self::assertFalse(AlterationRules::standalone(TableCommand::RemovePartitioning));
    }

    public function testReleaseRejectsUpgradePartitioningOutsideMySql57(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t FORCE');
        $this->expectException(InvalidStructure::class);
        AlterationRules::release($statement->origin, TableCommand::UpgradePartitioning);
    }
}
