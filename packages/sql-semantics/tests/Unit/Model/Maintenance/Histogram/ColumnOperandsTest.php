<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Maintenance\Histogram;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Maintenance\Histogram\ColumnOperands;
use SqlSemantics\Model\Statement\Maintenance\MySql\UpdateHistogramStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ColumnOperands::class)]
#[Medium]
final class ColumnOperandsTest extends TestCase
{
    public function testValidateRejectsAnotherRelationOccurrenceEvenForTheSameTable(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)'));
        $statement = $binder->bind('ANALYZE TABLE t UPDATE HISTOGRAM ON id');
        $query = $binder->bind('SELECT b.id FROM t a JOIN t b ON a.id=b.id');
        self::assertInstanceOf(UpdateHistogramStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $query);
        $column = $query->outputs[0]->expression;
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Reference\ColumnReference::class, $column);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        ColumnOperands::validate($statement->table, [$column]);
    }
}
