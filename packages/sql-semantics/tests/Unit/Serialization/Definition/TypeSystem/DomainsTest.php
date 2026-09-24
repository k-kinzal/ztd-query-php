<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\TypeSystem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\TypeSystem\Domain;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Domain\CreateDomainStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\TypeSystem\Domains;

#[CoversClass(Domains::class)]
#[Medium]
final class DomainsTest extends TestCase
{
    public function testWriteReturnsNullForUnrelatedRequests(): void
    {
        self::assertNull(Domains::write((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')));
    }

    #[TestWith(['CREATE DOMAIN "d" AS integer'])]
    #[TestWith(['ALTER DOMAIN "d" SET DEFAULT 1'])]
    #[TestWith(['ALTER DOMAIN "d" DROP DEFAULT'])]
    #[TestWith(['ALTER DOMAIN "d" DROP NOT NULL'])]
    #[TestWith(['ALTER DOMAIN "d" ADD NOT NULL'])]
    #[TestWith(['ALTER DOMAIN "d" DROP CONSTRAINT "c"'])]
    #[TestWith(['ALTER DOMAIN "d" VALIDATE CONSTRAINT "c"'])]
    public function testWriteProducesTheStatementText(string $sql): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
        self::assertSame($sql, Domains::write($statement)?->toString());
    }

    public function testCreateWritesClausesInCanonicalOrder(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE DOMAIN d int NOT NULL COLLATE "C" DEFAULT 1');
        self::assertInstanceOf(CreateDomainStatement::class, $statement);
        self::assertSame('CREATE DOMAIN "d" AS integer DEFAULT 1 COLLATE "C" NOT NULL', Domains::create($statement)->toString());
    }

    public function testConstraintWritesTheOptionalName(): void
    {
        self::assertSame('CONSTRAINT "c" NULL', Domains::constraint(new Domain\DomainNullable('c'))->toString());
        self::assertSame('NOT NULL', Domains::constraint(new Domain\DomainNotNull())->toString());
    }

    public function testAlterWritesTheTarget(): void
    {
        self::assertSame('ALTER DOMAIN "s"."d"', Domains::alter(new QualifiedName(['s', 'd']))->toString());
    }
}
