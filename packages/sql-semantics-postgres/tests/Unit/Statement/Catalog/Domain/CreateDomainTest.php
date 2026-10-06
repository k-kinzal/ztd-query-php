<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Domain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Domain\CreateDomain::class)]
#[Medium]
final class CreateDomainTest extends TestCase
{
    public function testRenderDropsAs(): void
    {
        self::assertSame('CREATE DOMAIN posint int4 CHECK (value > 0)', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE DOMAIN posint AS int4 CHECK (VALUE > 0)')->toString());
    }

    public function testDeriveStatementResolvesValue(): void
    {
        self::assertSame([], (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE DOMAIN posint AS int4 NOT NULL DEFAULT 1 CHECK (VALUE > 0)')->facts->diagnostics);
    }

    public function testDeriveStatementReportsAnotherColumn(): void
    {
        self::assertSame('Column x does not exist.', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE DOMAIN d int4 CHECK (x > 0)')->facts->diagnostics[0]->message());
    }

    public function testDeriveStatementReportsAUniqueConstraint(): void
    {
        self::assertSame('unique constraints not possible for domains', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE DOMAIN d int4 UNIQUE')->facts->diagnostics[0]->message());
    }

    public function testDeriveStatementReportsConflictingNullability(): void
    {
        self::assertSame('conflicting NULL/NOT NULL constraints', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE DOMAIN d int4 NOT NULL NULL')->facts->diagnostics[0]->message());
    }

    public function testDeriveRelationOffersAnEmptyRow(): void
    {
        $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE DOMAIN d int4 CHECK (VALUE > 0)');
        $statement = $operation->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Domain\CreateDomain::class, $statement);
        self::assertSame([], $operation->facts->relation($statement)->shape->slots);
    }
}
