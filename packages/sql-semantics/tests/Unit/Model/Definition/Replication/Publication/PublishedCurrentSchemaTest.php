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

#[CoversClass(Operand\PublishedCurrentSchema::class)]
#[Medium]
final class PublishedCurrentSchemaTest extends TestCase
{
    public function testTheCurrentSchemaIsWrittenAsAKeyword(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT, b INT)')))->bind('ALTER PUBLICATION p DROP TABLES IN SCHEMA CURRENT_SCHEMA');
        self::assertInstanceOf(Statement\AlterPublicationObjectsStatement::class, $statement);
        self::assertEquals([new Operand\PublishedCurrentSchema()], $statement->objects);
        self::assertSame('ALTER PUBLICATION "p" DROP TABLES IN SCHEMA CURRENT_SCHEMA', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }
}
