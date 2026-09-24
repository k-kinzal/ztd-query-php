<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Maintenance\PostgreSql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Maintenance\PostgreSql\AnalyzeStatement;
use SqlSemantics\Model\Statement\Maintenance\PostgreSql\MaintenanceTarget;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(MaintenanceTarget::class)]
#[Medium]
final class MaintenanceTargetTest extends TestCase
{
    public function testKeepsTheTableAndColumns(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT, b INT)')))->bind('ANALYZE t (b, a)');
        self::assertInstanceOf(AnalyzeStatement::class, $statement);
        self::assertSame(['b', 'a'], $statement->targets[0]->columns);
        self::assertSame('t', $statement->targets[0]->table->declaration->name);
    }

    public function testRejectsAnEmptyColumnName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('ANALYZE t');
        self::assertInstanceOf(AnalyzeStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new MaintenanceTarget($statement->targets[0]->table, ['']);
    }
}
