<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Rules\Catalog\DomainChecks::class)]
#[Medium]
final class DomainChecksTest extends TestCase
{
    public function testConstraintsReportsTwoDefaults(): void
    {
        self::assertSame('multiple default expressions', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE DOMAIN d int4 DEFAULT 1 DEFAULT 2')->facts->diagnostics[0]->message());
    }

    public function testConstraintsReportsDeferrability(): void
    {
        self::assertSame('specifying constraint deferrability not supported for domains', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE DOMAIN d int4 NOT NULL DEFERRABLE')->facts->diagnostics[0]->message());
    }

    public function testRuleOfAForeignKey(): void
    {
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Problem\CatalogMisuseRule::DomainForeignKey, (new \SqlSemantics\Platform\PostgreSql\Rules\Catalog\DomainChecks())->rule(\SqlSemantics\Platform\PostgreSql\Statement\Constraint\ConstraintKind::ForeignKey));
    }

    public function testRuleAcceptsACheck(): void
    {
        self::assertNull((new \SqlSemantics\Platform\PostgreSql\Rules\Catalog\DomainChecks())->rule(\SqlSemantics\Platform\PostgreSql\Statement\Constraint\ConstraintKind::Check));
    }

    public function testAttributesAcceptsTheDefaults(): void
    {
        self::assertSame([], (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER DOMAIN d ADD CHECK (VALUE > 0) NOT DEFERRABLE INITIALLY IMMEDIATE')->facts->diagnostics);
    }
}
