<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\MySqlTable;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\Definition\MySqlTable\PartitionDefinitions;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\MySqlTable\Column\AfterColumn;
use SqlSemantics\Model\Definition\MySqlTable\Partition\ListBound;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(PartitionDefinitions::class)]
#[Medium]
final class PartitionDefinitionsTest extends TestCase
{
    public function testReadBindsEveryDefinition(): void
    {
        $definitions = PartitionDefinitions::read((new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('ALTER TABLE t ADD PARTITION (PARTITION a VALUES IN (1), PARTITION b VALUES IN (2))')->find('standalone_alter_commands')[0], new Scope(new Identifiers(Dialect::MySql)));
        self::assertSame(['a', 'b'], array_map(static fn ($definition): string => $definition->name, $definitions));
    }

    public function testDefinitionReadsAQuotedSubpartitionName(): void
    {
        $definition = PartitionDefinitions::definition((new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('ALTER TABLE t ADD PARTITION (PARTITION a VALUES IN (1) (SUBPARTITION \'s\'))')->find('part_definition')[0], new Scope(new Identifiers(Dialect::MySql)));
        self::assertSame('s', $definition->subpartitions[0]->name);
    }

    public function testValuesReadsSingleValueTuples(): void
    {
        $values = PartitionDefinitions::values((new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('ALTER TABLE t ADD PARTITION (PARTITION a VALUES IN (1, 2))')->find('opt_part_values')[0], new Scope(new Identifiers(Dialect::MySql)));
        self::assertInstanceOf(ListBound::class, $values);
        self::assertSame([1, 1], array_map(count(...), $values->tuples));
    }

    public function testEntryDiagnosesMaxValueInAList(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::PartitionDefinition->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t ADD PARTITION (PARTITION a VALUES IN (MAXVALUE))');
    }

    public function testExpressionBindsAConstant(): void
    {
        $entry = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('ALTER TABLE t ADD PARTITION (PARTITION a VALUES IN (7))')->find('part_value_item')[0];
        $value = PartitionDefinitions::expression($entry, new Scope(new Identifiers(Dialect::MySql)));
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\Literal::class, $value);
        self::assertSame('7', $value->text);
    }

    public function testPropertiesKeepTheLastOption(): void
    {
        $properties = PartitionDefinitions::properties((new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('ALTER TABLE t ADD PARTITION (PARTITION a ENGINE = x STORAGE ENGINE = y)')->find('opt_part_options')[0], new Scope(new Identifiers(Dialect::MySql)));
        self::assertSame('y', $properties->engine);
    }

    public function testBuildDiagnosesAViolatedInvariant(): void
    {
        $node = (new DialectParser(Dialect::MySql, 'mysql-8.4.7'))->parse('ALTER TABLE t FORCE')->find('alter_list_item')[0];
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::TableAlteration->message());
        PartitionDefinitions::build(static fn (): AfterColumn => new AfterColumn(''), $node, InputViolation::TableAlteration);
    }
}
