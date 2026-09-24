<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\MySqlTable;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\Definition\MySqlTable\PartitionSchemes;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\MySqlTable\Partition\ColumnsPartitioning;
use SqlSemantics\Model\Definition\MySqlTable\Table\RepartitionTable;
use SqlSemantics\Model\Statement\Definition\MySql\Table\AlterTableStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Schema\Partition\PartitionStrategy;
use SqlSemantics\SchemaBuilder;

#[CoversClass(PartitionSchemes::class)]
#[Medium]
final class PartitionSchemesTest extends TestCase
{
    public function testReadReturnsNullWithoutPartitioning(): void
    {
        self::assertNull(PartitionSchemes::read((new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('ALTER TABLE t FORCE'), new Scope(new Identifiers(Dialect::MySql))));
    }

    public function testBindDiagnosesAMissingDefinitionList(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::PartitionDefinition->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t PARTITION BY LIST (id)');
    }

    public function testFunctionDiagnosesAnUnknownKeyAlgorithm(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::PartitionDefinition->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t PARTITION BY KEY ALGORITHM = 3 (id)');
    }

    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testFunctionReadsColumnsOnEveryRelease(string $version): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE t(a INT, b INT)')))->bind('ALTER TABLE t PARTITION BY RANGE COLUMNS (a, b) (PARTITION p VALUES LESS THAN (1, 2))');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(RepartitionTable::class, $alteration);
        self::assertInstanceOf(ColumnsPartitioning::class, $alteration->partitioning->function);
        self::assertSame(['a', 'b'], $alteration->partitioning->function->columns);
    }

    public function testStrategyReadsRangeOrList(): void
    {
        self::assertSame(PartitionStrategy::List, PartitionSchemes::strategy(['LIST', 'COLUMNS']));
    }
}
