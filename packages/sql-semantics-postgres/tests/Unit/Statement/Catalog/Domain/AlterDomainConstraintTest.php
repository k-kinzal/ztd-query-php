<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Domain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Domain\AlterDomainConstraint::class)]
#[Medium]
final class AlterDomainConstraintTest extends TestCase
{
    public function testRenderWritesTheConstraint(): void
    {
        self::assertSame('ALTER DOMAIN posint ADD CONSTRAINT positive CHECK (value > 0) NOT VALID', (new \SqlSemantics\Testing\PostgreSql\Inspection())->diagnose('ALTER DOMAIN posint ADD CONSTRAINT positive CHECK (VALUE > 0) NOT VALID')->toString());
    }

    public function testRenderWritesATableConstraintOf16(): void
    {
        self::assertSame('ALTER DOMAIN d ADD CONSTRAINT c CHECK (value > 0)', (new \SqlSemantics\Testing\PostgreSql\Inspection('pg-16.6'))->diagnose('ALTER DOMAIN d ADD CONSTRAINT c CHECK (VALUE > 0)')->toString());
    }

    public function testDeriveStatementReportsAUniqueConstraintOf16(): void
    {
        self::assertSame('unique constraints not possible for domains', (new \SqlSemantics\Testing\PostgreSql\Inspection('pg-16.6'))->diagnose('ALTER DOMAIN d ADD UNIQUE (a)')->facts->diagnostics[0]->message());
    }

    public function testDeriveStatementReportsAnImproperName(): void
    {
        self::assertSame('improper qualified name (too many dotted names): a.b.c.d', (new \SqlSemantics\Testing\PostgreSql\Inspection())->diagnose('ALTER DOMAIN a.b.c.d ADD CHECK (VALUE > 1)')->facts->diagnostics[0]->message());
    }

    public function testDeriveRelationOffersAnEmptyRow(): void
    {
        $operation = (new \SqlSemantics\Testing\PostgreSql\Inspection())->diagnose('ALTER DOMAIN d ADD NOT NULL');
        $statement = $operation->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Domain\AlterDomainConstraint::class, $statement);
        self::assertSame([], $operation->facts->relation($statement)->shape->slots);
    }
}
