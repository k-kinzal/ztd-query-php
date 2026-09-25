<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Inspection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Inspection\SessionInspection;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Inspection\Replication\ShowReplicaStatusStatement;
use SqlSemantics\Model\Statement\Inspection\Schema\ShowDatabasesStatement;
use SqlSemantics\Model\Statement\Inspection\Session\ShowVariablesStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SessionInspection::class)]
#[Medium]
final class SessionInspectionTest extends TestCase
{
    /**
     * @param class-string<ShowVariablesStatement|ShowReplicaStatusStatement|ShowDatabasesStatement> $expected
     */
    #[TestWith(['mysql-5.6.51', 'SHOW /* all */ GLOBAL VARIABLES', ShowVariablesStatement::class])]
    #[TestWith(['mysql-8.0.44', 'SHOW GLOBAL VARIABLES', ShowVariablesStatement::class])]
    #[TestWith(['mysql-5.7.44', 'SHOW SLAVE STATUS', ShowReplicaStatusStatement::class])]
    #[TestWith(['mysql-9.1.0', 'SHOW REPLICA STATUS', ShowReplicaStatusStatement::class])]
    #[TestWith(['mysql-9.1.0', 'SHOW DATABASES', ShowDatabasesStatement::class])]
    public function testBindRoutesTheSharedAndPerFormRules(string $version, string $sql, string $expected): void
    {
        self::assertInstanceOf($expected, (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build()))->bind($sql));
    }

    #[TestWith(['show errors', 'SHOW ERRORS'])]
    #[TestWith(['show count(*) warnings', 'SHOW COUNT(*) WARNINGS'])]
    #[TestWith(['show engine innodb status', 'SHOW ENGINE `innodb` STATUS'])]
    public function testBindReadsLowercaseShowForms(string $sql, string $expected): void
    {
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize((new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind($sql)));
    }
}
