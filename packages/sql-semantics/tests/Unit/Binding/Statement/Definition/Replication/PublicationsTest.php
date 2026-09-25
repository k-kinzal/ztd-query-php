<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Replication as Statement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Binding\Statement\Definition\Replication\Publications::class)]
#[Medium]
final class PublicationsTest extends TestCase
{
    #[TestWith(['CREATE PUBLICATION p', Statement\CreatePublicationStatement::class])]
    #[TestWith(['CREATE PUBLICATION "ALL" FOR ALL TABLES', Statement\CreateAllTablesPublicationStatement::class])]
    #[TestWith(['CREATE PUBLICATION p FOR TABLE t WITH (publish = \'\')', Statement\CreateObjectsPublicationStatement::class])]
    #[TestWith(['ALTER PUBLICATION p SET (publish = \'insert\')', Statement\AlterPublicationOptionsStatement::class])]
    #[TestWith(['ALTER PUBLICATION p SET TABLE t', Statement\AlterPublicationObjectsStatement::class])]
    public function testBindSeparatesEachForm(string $sql, string $class): void
    {
        $binder = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT, b INT)')));
        $statement = $binder->bind($sql);
        self::assertSame($class, $statement::class);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    #[TestWith(['ALTER PUBLICATION p DROP TABLE t (a)'])]
    #[TestWith(['ALTER PUBLICATION p DROP TABLE t WHERE (a > 0)'])]
    #[TestWith(['ALTER PUBLICATION p ADD TABLE t (a), TABLES IN SCHEMA s'])]
    public function testBindDiagnosesAnImpossibleObjectChange(string $sql): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::PublicationObject->message());
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT, b INT)')))->bind($sql);
    }
}
