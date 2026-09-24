<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Maintenance\PostgreSql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Maintenance\PostgreSql\AnalyzeStatement;
use SqlSemantics\Model\Statement\Maintenance\PostgreSql\MaintenanceTargets;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(MaintenanceTargets::class)]
#[Medium]
final class MaintenanceTargetsTest extends TestCase
{
    public function testValidateAcceptsPostgreSqlTargets(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)')))->bind('ANALYZE t');
        self::assertInstanceOf(AnalyzeStatement::class, $statement);
        MaintenanceTargets::validate($statement->origin, $statement->targets);
        self::assertCount(1, $statement->targets);
    }

    public function testValidateRequiresPostgreSql(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ANALYZE');
        $this->expectException(InvalidStructure::class);
        MaintenanceTargets::validate(new Origin($statement->origin->scopeId, $statement->origin->source, Dialect::MySql), []);
    }

    public function testTableRejectsARelationOfAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ANALYZE');
        $mysql = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind('ANALYZE TABLE t');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Maintenance\MySql\AnalyzeTablesStatement::class, $mysql);
        $this->expectException(InvalidStructure::class);
        MaintenanceTargets::table($statement->origin, $mysql->tables[0]);
    }
}
