<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Replication\Publication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Replication\Publication as Operand;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Replication as Statement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Operand\PublicationObjectChange::class)]
#[Medium]
final class PublicationObjectChangeTest extends TestCase
{
    #[TestWith([Operand\PublicationObjectChange::Add])]
    #[TestWith([Operand\PublicationObjectChange::Set])]
    #[TestWith([Operand\PublicationObjectChange::Drop])]
    public function testEachChangeSurvivesBindingAndSerialization(Operand\PublicationObjectChange $change): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT, b INT)')))->bind('ALTER PUBLICATION p ' . $change->value . ' TABLE t');
        self::assertInstanceOf(Statement\AlterPublicationObjectsStatement::class, $statement);
        self::assertSame($change, $statement->change);
        self::assertSame('ALTER PUBLICATION "p" ' . $change->value . ' TABLE "public"."t"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }
}
