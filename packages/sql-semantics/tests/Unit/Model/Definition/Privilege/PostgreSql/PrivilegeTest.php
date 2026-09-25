<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Privilege\PostgreSql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Privilege;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Privilege\GrantPrivilegesStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Privilege::class)]
#[Medium]
final class PrivilegeTest extends TestCase
{
    public function testRepresentsEveryPrivilegeType(): void
    {
        self::assertSame(
            ['SELECT', 'INSERT', 'UPDATE', 'DELETE', 'TRUNCATE', 'REFERENCES', 'TRIGGER', 'EXECUTE', 'USAGE', 'CREATE', 'CONNECT', 'TEMPORARY', 'SET', 'ALTER SYSTEM', 'MAINTAIN', 'ALL PRIVILEGES'],
            array_column(Privilege::cases(), 'value'),
        );
    }

    #[TestWith([Privilege::Select, true])]
    #[TestWith([Privilege::Insert, true])]
    #[TestWith([Privilege::Update, true])]
    #[TestWith([Privilege::References, true])]
    #[TestWith([Privilege::All, true])]
    #[TestWith([Privilege::Delete, false])]
    #[TestWith([Privilege::Truncate, false])]
    #[TestWith([Privilege::Trigger, false])]
    #[TestWith([Privilege::Execute, false])]
    #[TestWith([Privilege::Usage, false])]
    #[TestWith([Privilege::Create, false])]
    #[TestWith([Privilege::Connect, false])]
    #[TestWith([Privilege::Temporary, false])]
    #[TestWith([Privilege::Set, false])]
    #[TestWith([Privilege::AlterSystem, false])]
    #[TestWith([Privilege::Maintain, false])]
    public function testColumnarReportsWhetherAColumnListIsAccepted(Privilege $privilege, bool $columnar): void
    {
        self::assertSame($columnar, $privilege->columnar());
    }

    #[TestWith(['GRANT TEMP ON DATABASE d TO a', Privilege::Temporary, 'GRANT TEMPORARY ON DATABASE "d" TO "a"'])]
    #[TestWith(['GRANT ALL ON DATABASE d TO a', Privilege::All, 'GRANT ALL PRIVILEGES ON DATABASE "d" TO "a"'])]
    #[TestWith(['GRANT ALTER SYSTEM ON PARAMETER work_mem TO a', Privilege::AlterSystem, 'GRANT ALTER SYSTEM ON PARAMETER "work_mem" TO "a"'])]
    public function testBindsSynonymsToOneCaseAndWritesTheCanonicalWord(string $sql, Privilege $privilege, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind($sql);
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        self::assertSame($privilege, $statement->privileges[0]->privilege);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }
}
