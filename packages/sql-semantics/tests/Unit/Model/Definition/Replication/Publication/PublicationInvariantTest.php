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
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Operand\PublicationInvariant::class)]
#[Medium]
final class PublicationInvariantTest extends TestCase
{
    #[TestWith([Dialect::MySql, 'p'])]
    #[TestWith([Dialect::PostgreSql, ''])]
    public function testIdentityRejectsAnotherLanguageOrAnEmptyName(Dialect $dialect, string $name): void
    {
        $origin = (new Binder((new SchemaBuilder($dialect))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        Operand\PublicationInvariant::identity($origin, $name);
    }

    public function testObjectsRejectsATwiceListedTableWithAFilter(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT, b INT)')))->bind('CREATE PUBLICATION p FOR TABLE t WHERE (a > 0)');
        self::assertInstanceOf(Statement\CreateObjectsPublicationStatement::class, $statement);
        self::assertCount(1, $statement->objects);
        $this->expectException(InvalidStructure::class);
        Operand\PublicationInvariant::objects([$statement->objects[0], $statement->objects[0]]);
    }

    public function testObjectsRejectsColumnsNextToASchema(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT, b INT)')))->bind('CREATE PUBLICATION p FOR TABLE t (a)');
        self::assertInstanceOf(Statement\CreateObjectsPublicationStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        Operand\PublicationInvariant::objects([$statement->objects[0], new Operand\PublishedSchema('s')]);
    }

    public function testObjectsRequiresAnObject(): void
    {
        $this->expectException(InvalidStructure::class);
        Operand\PublicationInvariant::objects([]);
    }
}
