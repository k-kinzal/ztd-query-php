<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Catalog;
use SqlSemantics\Model\Definition\Catalog\Kind;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Catalog as Statement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Catalog\OperatorSetIdentity::class)]
#[Medium]
final class OperatorSetIdentityTest extends TestCase
{
    #[TestWith(['ALTER OPERATOR FAMILY app.ints USING btree SET SCHEMA archive', Kind\OperatorSetKind::OperatorFamily])]
    #[TestWith(['ALTER OPERATOR CLASS app.ints USING btree SET SCHEMA archive', Kind\OperatorSetKind::OperatorClass])]
    public function testRetainsTheNameAndAccessMethod(string $sql, Kind\OperatorSetKind $kind): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
        self::assertInstanceOf(Statement\SetObjectSchemaStatement::class, $statement);
        self::assertEquals(new Catalog\OperatorSetIdentity($kind, new QualifiedName(['app', 'ints']), 'btree'), $statement->object);
        self::assertStringContainsString('"app"."ints" USING "btree" SET SCHEMA "archive"', $statement->toString());
    }

    public function testRejectsAnEmptyAccessMethod(): void
    {
        $this->expectException(InvalidStructure::class);
        new Catalog\OperatorSetIdentity(Kind\OperatorSetKind::OperatorClass, new QualifiedName(['ints']), '');
    }
}
