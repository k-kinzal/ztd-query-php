<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Privilege\PostgreSql\Target;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\SchemaObjectClass;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Target\SchemaObjectTargets;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Privilege\GrantPrivilegesStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SchemaObjectTargets::class)]
#[Medium]
final class SchemaObjectTargetsTest extends TestCase
{
    public function testRetainsTheClassAndQualifiedNamesInRequestOrder(): void
    {
        $money = new QualifiedName(['app', 'money']);
        $plain = new QualifiedName(['tag']);
        $targets = new SchemaObjectTargets(SchemaObjectClass::Type, [$money, $plain]);
        self::assertSame(SchemaObjectClass::Type, $targets->class);
        self::assertSame([$money, $plain], $targets->names);
        self::assertSame(['app', 'money'], $targets->names[0]->parts);
    }

    public function testReadsTheSequencesOfABoundGrant(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('GRANT USAGE ON SEQUENCE s, app.t TO a');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        self::assertEquals(new SchemaObjectTargets(SchemaObjectClass::Sequence, [new QualifiedName(['s']), new QualifiedName(['app', 't'])]), $statement->target);
        self::assertSame('GRANT USAGE ON SEQUENCE "s", "app"."t" TO "a"', $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }
}
