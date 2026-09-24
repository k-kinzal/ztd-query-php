<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Replication\Publication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Replication\Publication as Operand;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Replication as Statement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Operand\PublicationMember::class)]
#[Medium]
final class PublicationMemberTest extends TestCase
{
    public function testEveryObjectKindIsAPublicationMember(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT, b INT)')))->bind('CREATE PUBLICATION p FOR TABLE t, TABLES IN SCHEMA s, CURRENT_SCHEMA');
        self::assertInstanceOf(Statement\CreateObjectsPublicationStatement::class, $statement);
        self::assertContainsOnlyInstancesOf(Operand\PublicationMember::class, $statement->objects);
        self::assertInstanceOf(Operand\PublishedCurrentSchema::class, $statement->objects[2]);
    }
}
