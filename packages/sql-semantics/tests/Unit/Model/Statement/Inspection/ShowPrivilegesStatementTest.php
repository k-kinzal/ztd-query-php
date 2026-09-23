<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Inspection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Query\Inspection\ServerTextColumn;
use SqlSemantics\Model\Statement\Inspection\ShowPrivilegesStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ShowPrivilegesStatement::class)]
#[Medium]
final class ShowPrivilegesStatementTest extends TestCase
{
    public function testResultColumnsDescribeTheServerMetadataWithoutFetchingIt(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW PRIVILEGES');
        self::assertInstanceOf(ShowPrivilegesStatement::class, $statement);
        self::assertSame(['Privilege', 'Context', 'Comment'], array_column($statement->resultColumns(), 'name'));
        $expression = $statement->resultColumns()[0]->expression;
        self::assertInstanceOf(ServerTextColumn::class, $expression);
        self::assertSame($statement->scopeId, $expression->scopeId);
        self::assertSame('varchar', $expression->type->name);
        self::assertSame([], $expression->inputs());
    }

    public function testWithOriginRetainsTheInspectionOperation(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW PRIVILEGES');
        self::assertInstanceOf(ShowPrivilegesStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame('SHOW PRIVILEGES', $copy->toString());
        self::assertSame($statement->kind, $copy->kind);
    }

    public function testRejectsAnOriginFromAnotherDialect(): void
    {
        $origin = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1')->origin;
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        new ShowPrivilegesStatement($origin);
    }

}
