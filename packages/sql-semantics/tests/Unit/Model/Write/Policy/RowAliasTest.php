<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Write\Policy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\ProposedRow;
use SqlSemantics\Model\Statement\Insert\InsertSelectStatement;
use SqlSemantics\Model\Statement\Insert\InsertSetStatement;
use SqlSemantics\Model\Statement\Insert\InsertValuesStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Model\Write\Policy\MySqlInsertion;
use SqlSemantics\Model\Write\Policy\RowAlias;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RowAlias::class)]
#[Medium]
final class RowAliasTest extends TestCase
{
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testNamesTheProposedRowOfAValuesInsertion(string $release): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $release))->build('CREATE TABLE t (id INT PRIMARY KEY, a INT, b INT)'));
        $statement = $binder->bind('INSERT INTO t (id, a) VALUES (1, 2) AS n ON DUPLICATE KEY UPDATE a = n.a');
        self::assertInstanceOf(InsertValuesStatement::class, $statement);
        self::assertInstanceOf(MySqlInsertion::class, $statement->policy);
        $alias = $statement->policy->rowAlias;
        self::assertInstanceOf(RowAlias::class, $alias);
        self::assertSame('n', $alias->row->alias);
        self::assertSame([], $alias->columns);
        self::assertSame($statement->insertion->target->id, $alias->row->target->id);
        self::assertSame(['id', 'a'], array_column($alias->row->declaration->columns, 'name'));
        self::assertInstanceOf(\SqlSemantics\Model\Write\Conflict\DoUpdate::class, $statement->conflicts[0]);
        $assignment = $statement->conflicts[0]->assignments[0];
        self::assertInstanceOf(\SqlSemantics\Model\Write\Assignment\ScalarAssignment::class, $assignment);
        self::assertSame($alias->row->id, $assignment->value->columnBinding()?->relationId);
        self::assertSame('INSERT INTO `t`(`id`, `a`) VALUES (1, 2) AS `n` ON DUPLICATE KEY UPDATE `a` = `n`.`a`', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testColumnAliasesRenameTheProposedColumnsInInsertionOrder(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (id INT PRIMARY KEY, a INT, b INT)'));
        $statement = $binder->bind('INSERT INTO t SET id = 1, a = 2 AS n(p, q) ON DUPLICATE KEY UPDATE b = q + a');
        self::assertInstanceOf(InsertSetStatement::class, $statement);
        self::assertInstanceOf(MySqlInsertion::class, $statement->policy);
        self::assertSame(['p', 'q'], $statement->policy->rowAlias?->columns);
        self::assertSame(['p', 'q'], array_column($statement->policy->rowAlias->row->declaration->columns, 'name'));
        self::assertInstanceOf(\SqlSemantics\Model\Write\Conflict\DoUpdate::class, $statement->conflicts[0]);
        $assignment = $statement->conflicts[0]->assignments[0];
        self::assertInstanceOf(\SqlSemantics\Model\Write\Assignment\ScalarAssignment::class, $assignment);
        $sum = $assignment->value->inputs();
        self::assertSame($statement->policy->rowAlias->row->id, $sum[0]->columnBinding()?->relationId);
        self::assertSame($statement->insertion->target->id, $sum[1]->columnBinding()?->relationId);
        self::assertSame('INSERT INTO `t` SET `id` = 1, `a` = 2 AS `n`(`p`, `q`) ON DUPLICATE KEY UPDATE `b` = (`q` + `a`)', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testRequiresAnAlias(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (id INT)')))->bind('INSERT INTO t VALUES (1) AS n');
        self::assertInstanceOf(InsertValuesStatement::class, $statement);
        self::assertInstanceOf(MySqlInsertion::class, $statement->policy);
        $row = $statement->policy->rowAlias?->row;
        self::assertInstanceOf(ProposedRow::class, $row);
        $this->expectException(InvalidStructure::class);
        new RowAlias(new ProposedRow($row->id, $row->scopeId, $row->declaration, null, $row->source, $row->target));
    }

    public function testRejectsColumnAliasesThatDisagreeWithTheProposedColumns(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (id INT)')))->bind('INSERT INTO t VALUES (1) AS n(x)');
        self::assertInstanceOf(InsertValuesStatement::class, $statement);
        self::assertInstanceOf(MySqlInsertion::class, $statement->policy);
        $row = $statement->policy->rowAlias?->row;
        self::assertInstanceOf(ProposedRow::class, $row);
        self::assertSame(['x'], (new RowAlias($row, ['x']))->columns);
        $this->expectException(InvalidStructure::class);
        new RowAlias($row, ['y']);
    }

    public function testRejectsRepeatedColumnNames(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (id INT, a INT)')))->bind('INSERT INTO t VALUES (1, 2) AS n');
        self::assertInstanceOf(InsertValuesStatement::class, $statement);
        self::assertInstanceOf(MySqlInsertion::class, $statement->policy);
        $row = $statement->policy->rowAlias?->row;
        self::assertInstanceOf(ProposedRow::class, $row);
        $columns = [$row->declaration->columns[0], $row->declaration->columns[1]->withName('ID')];
        $this->expectException(InvalidStructure::class);
        new RowAlias(new ProposedRow($row->id, $row->scopeId, new \SqlSemantics\Schema\TableDefinition('', 'n', $columns, [], $row->source), 'n', $row->source, $row->target));
    }

    public function testAnInsertSelectCannotNameItsProposedRow(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (id INT)'));
        $aliased = $binder->bind('INSERT INTO t VALUES (1) AS n');
        $select = $binder->bind('INSERT INTO t SELECT 1');
        self::assertInstanceOf(InsertValuesStatement::class, $aliased);
        self::assertInstanceOf(InsertSelectStatement::class, $select);
        $this->expectException(InvalidStructure::class);
        new InsertSelectStatement($select->origin, $aliased->insertion, $select->query, policy: $aliased->policy);
    }

    public function testAReleaseBefore8019CannotNameTheProposedRow(): void
    {
        $aliased = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build('CREATE TABLE t (id INT)')))->bind('INSERT INTO t VALUES (1) AS n');
        $legacy = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build('CREATE TABLE t (id INT)')))->bind('INSERT INTO t VALUES (1)');
        self::assertInstanceOf(InsertValuesStatement::class, $aliased);
        self::assertInstanceOf(InsertValuesStatement::class, $legacy);
        self::assertSame($aliased->policy, (new InsertValuesStatement($aliased->origin, $aliased->insertion, $aliased->rows, policy: $aliased->policy))->policy);
        $this->expectException(InvalidStructure::class);
        new InsertValuesStatement($legacy->origin, $aliased->insertion, $aliased->rows, policy: $aliased->policy);
    }

    public function testTheAliasBelongsToTheInsertionTarget(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t (id INT); CREATE TABLE u (id INT)'));
        $aliased = $binder->bind('INSERT INTO t VALUES (1) AS n');
        $other = $binder->bind('INSERT INTO u VALUES (1)');
        self::assertInstanceOf(InsertValuesStatement::class, $aliased);
        self::assertInstanceOf(InsertValuesStatement::class, $other);
        $this->expectException(InvalidStructure::class);
        new InsertValuesStatement($aliased->origin, $other->insertion, $other->rows, policy: $aliased->policy);
    }
}
