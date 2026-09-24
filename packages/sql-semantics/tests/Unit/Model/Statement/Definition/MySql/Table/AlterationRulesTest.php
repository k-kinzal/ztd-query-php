<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\MySql\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\IndexLock;
use SqlSemantics\Model\Definition\MySqlTable\Table\TableCommand;
use SqlSemantics\Model\Definition\MySqlTable\TableAlgorithm;
use SqlSemantics\Model\Statement\Definition\MySql\Table\AlterationRules;
use SqlSemantics\Model\Statement\Definition\MySql\Table\AlterTableStatement;
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

    #[TestWith(['ALTER TABLE t ADD PARTITION (PARTITION p9)'])]
    #[TestWith(['ALTER TABLE t ADD PARTITION PARTITIONS 2'])]
    #[TestWith(['ALTER TABLE t DROP PARTITION p0'])]
    #[TestWith(['ALTER TABLE t ANALYZE PARTITION p0'])]
    #[TestWith(['ALTER TABLE t CHECK PARTITION ALL'])]
    #[TestWith(['ALTER TABLE t REPAIR PARTITION p0'])]
    #[TestWith(['ALTER TABLE t TRUNCATE PARTITION p0'])]
    #[TestWith(['ALTER TABLE t COALESCE PARTITION 1'])]
    #[TestWith(['ALTER TABLE t REORGANIZE PARTITION p0 INTO (PARTITION p5)'])]
    #[TestWith(['ALTER TABLE t EXCHANGE PARTITION p0 WITH TABLE t2'])]
    #[TestWith(['ALTER TABLE t DISCARD PARTITION p0 TABLESPACE'])]
    #[TestWith(['ALTER TABLE t SECONDARY_LOAD'])]
    #[TestWith(['ALTER TABLE t DISCARD TABLESPACE'])]
    public function testStandaloneClassifiesEveryPartitionAndTablespaceCommand(string $sql): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT, c INT, KEY i (n), CONSTRAINT k CHECK (n > 0)) PARTITION BY HASH (id) PARTITIONS 4; CREATE TABLE t2(id INT, n INT, c INT, KEY i (n))')))->bind($sql);
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        self::assertTrue(AlterationRules::standalone($statement->alterations[0]));
    }

    #[TestWith(['ALTER TABLE t ADD COLUMN d INT'])]
    #[TestWith(['ALTER TABLE t2 PARTITION BY HASH (id)'])]
    #[TestWith(['ALTER TABLE t DROP INDEX i'])]
    public function testStandaloneLeavesOrdinaryAlterationsCombinable(string $sql): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT, c INT, KEY i (n), CONSTRAINT k CHECK (n > 0)) PARTITION BY HASH (id) PARTITIONS 4; CREATE TABLE t2(id INT, n INT, c INT, KEY i (n))')))->bind($sql);
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        self::assertFalse(AlterationRules::standalone($statement->alterations[0]));
    }

    public function testCombinationAcceptsSeveralOrdinaryAlterationsAndATrailingPartitioningChange(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT, c INT, KEY i (n), CONSTRAINT k CHECK (n > 0)) PARTITION BY HASH (id) PARTITIONS 4; CREATE TABLE t2(id INT, n INT, c INT, KEY i (n))')))->bind('ALTER TABLE t2 ADD COLUMN d INT, DROP COLUMN c PARTITION BY HASH (id)');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        self::assertCount(3, $statement->alterations);
    }

    public function testCombinationRejectsAStandaloneCommandWithAnotherAlteration(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT, c INT, KEY i (n), CONSTRAINT k CHECK (n > 0)) PARTITION BY HASH (id) PARTITIONS 4; CREATE TABLE t2(id INT, n INT, c INT, KEY i (n))')))->bind('ALTER TABLE t DISCARD TABLESPACE');
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('A partition or tablespace command is the only alteration of its statement.');
        AlterationRules::combination($statement->origin, [TableCommand::DiscardTablespace, TableCommand::Force], TableAlgorithm::Default, IndexLock::Default);
    }

    #[TestWith(['mysql-5.7.44', 'ALTER TABLE t RENAME COLUMN c TO d'])]
    #[TestWith(['mysql-5.7.44', 'ALTER TABLE t ALTER COLUMN c SET INVISIBLE'])]
    #[TestWith(['mysql-5.7.44', 'ALTER TABLE t ALTER INDEX i INVISIBLE'])]
    #[TestWith(['mysql-5.7.44', 'ALTER TABLE t ALTER CHECK k NOT ENFORCED'])]
    #[TestWith(['mysql-5.7.44', 'ALTER TABLE t SECONDARY_LOAD'])]
    #[TestWith(['mysql-5.7.44', 'ALTER TABLE t DROP CHECK k'])]
    #[TestWith(['mysql-5.7.44', 'ALTER TABLE t DROP CONSTRAINT k'])]
    #[TestWith(['mysql-5.6.51', 'ALTER TABLE t RENAME INDEX i TO j'])]
    #[TestWith(['mysql-5.6.51', 'ALTER TABLE t DISCARD PARTITION p0 TABLESPACE'])]
    public function testReleaseRejectsAnAlterationOutsideItsReleases(string $release, string $sql): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT, c INT, KEY i (n), CONSTRAINT k CHECK (n > 0)) PARTITION BY HASH (id) PARTITIONS 4; CREATE TABLE t2(id INT, n INT, c INT, KEY i (n))')))->bind($sql);
        $older = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $release))->build('CREATE TABLE t(id INT)')))->bind('ALTER TABLE t FORCE');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        AlterationRules::release($older->origin, $statement->alterations[0]);
    }

    #[TestWith(['mysql-5.7.44', 'ALTER TABLE t UPGRADE PARTITIONING'])]
    #[TestWith(['mysql-5.7.44', 'ALTER TABLE t DROP INDEX i'])]
    #[TestWith(['mysql-5.7.44', 'ALTER TABLE t RENAME INDEX i TO j'])]
    public function testReleaseAcceptsAnAlterationOfTheRelease(string $release, string $sql): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $release))->build('CREATE TABLE t(id INT, n INT, c INT, KEY i (n), CONSTRAINT k CHECK (n > 0)) PARTITION BY HASH (id) PARTITIONS 4; CREATE TABLE t2(id INT, n INT, c INT, KEY i (n))')))->bind($sql);
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        self::assertCount(1, $statement->alterations);
    }
}
