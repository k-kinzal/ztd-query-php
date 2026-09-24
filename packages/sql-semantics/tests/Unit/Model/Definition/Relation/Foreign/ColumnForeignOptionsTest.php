<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Relation\Foreign;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Relation\Foreign;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\CreateForeignTableStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Foreign\ColumnForeignOptions::class)]
#[Medium]
final class ColumnForeignOptionsTest extends TestCase
{
    public function testRetainsTheColumnAndItsOptions(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("CREATE FOREIGN TABLE ft (a integer OPTIONS (column_name 'remote_a')) SERVER s");
        self::assertInstanceOf(CreateForeignTableStatement::class, $statement);
        self::assertSame('a', $statement->columnOptions[0]->column);
        self::assertSame('column_name', $statement->columnOptions[0]->options[0]->name);
        self::assertSame('CREATE FOREIGN TABLE "public"."ft"("a" integer OPTIONS("column_name" \'remote_a\')) SERVER "s"', $statement->toString());
    }
}
