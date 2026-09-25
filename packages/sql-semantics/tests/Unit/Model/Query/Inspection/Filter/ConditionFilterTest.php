<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Inspection\Filter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Query\Inspection\Filter\ConditionFilter;
use SqlSemantics\Model\Query\Inspection\MetadataColumn;
use SqlSemantics\Model\Scalar\Operator\BinaryExpression;
use SqlSemantics\Model\Statement\Inspection\Schema\ShowDatabasesStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ConditionFilter::class)]
#[Medium]
final class ConditionFilterTest extends TestCase
{
    public function testConditionRefersToTheListingResultFieldsByLabel(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SHOW DATABASES WHERE `Database` <> 'mysql'");
        self::assertInstanceOf(ShowDatabasesStatement::class, $statement);
        self::assertInstanceOf(ConditionFilter::class, $statement->filter);
        $condition = $statement->filter->condition;
        self::assertInstanceOf(BinaryExpression::class, $condition);
        self::assertInstanceOf(MetadataColumn::class, $condition->inputs()[0]);
        self::assertSame('Database', $condition->inputs()[0]->spelling());
        self::assertSame("SHOW DATABASES WHERE (`Database` <> 'mysql')", (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testRejectsAConditionFromAnotherDialect(): void
    {
        $select = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1');
        self::assertInstanceOf(BoundSelect::class, $select);
        $this->expectException(InvalidStructure::class);
        new ConditionFilter($select->outputs[0]->expression);
    }
}
