<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Domain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Domain\AlterDomainNullabilityStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AlterDomainNullabilityStatement::class)]
#[Medium]
final class AlterDomainNullabilityStatementTest extends TestCase
{
    #[TestWith(['ALTER DOMAIN d SET NOT NULL', true])]
    #[TestWith(['ALTER DOMAIN d DROP NOT NULL', false])]
    public function testBindsTheOperandsAndWritesThemBack(string $sql, bool $notNull): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
        self::assertInstanceOf(AlterDomainNullabilityStatement::class, $statement);
        self::assertSame($notNull, $statement->notNull);
        self::assertSame(str_replace(' d ', ' "d" ', $sql), (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DOMAIN d SET NOT NULL');
        self::assertInstanceOf(AlterDomainNullabilityStatement::class, $statement);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($statement->withOrigin($statement->origin)));
    }

    public function testWithDomainReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DOMAIN d SET NOT NULL');
        self::assertInstanceOf(AlterDomainNullabilityStatement::class, $statement);
        self::assertSame('ALTER DOMAIN "s"."e" SET NOT NULL', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withDomain(new QualifiedName(['s', 'e']))));
    }

    public function testWithNotNullReplacesTheOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DOMAIN d SET NOT NULL');
        self::assertInstanceOf(AlterDomainNullabilityStatement::class, $statement);
        self::assertFalse($statement->withNotNull(false)->notNull);
        self::assertTrue($statement->notNull);
    }

    public function testRejectsAnOverQualifiedDomain(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER DOMAIN d SET NOT NULL');
        self::assertInstanceOf(AlterDomainNullabilityStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withDomain(new QualifiedName(['a', 'b', 'c']));
    }
}
