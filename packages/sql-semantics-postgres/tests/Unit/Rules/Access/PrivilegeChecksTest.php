<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Access;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Rules\Access\PrivilegeChecks::class)]
#[Medium]
final class PrivilegeChecksTest extends TestCase
{
    public function testPrivilegesAcceptsTheRightsOfEachKind(): void
    {
        self::assertSame([], array_map(static fn (\SqlSemantics\Statement\Fact\Diagnostic $diagnostic): string => $diagnostic->message(), (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('GRANT insert, SELECT, update, delete, truncate, REFERENCES, trigger, maintain, usage, rule ON t TO joe')->facts->diagnostics));
    }

    public function testPrivilegesReportsARightOfAnotherKind(): void
    {
        self::assertSame(['invalid privilege type TEMP for sequence', 'invalid privilege type CONNECT for sequence'], array_map(static fn (\SqlSemantics\Statement\Fact\Diagnostic $diagnostic): string => $diagnostic->message(), (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('GRANT temporary, connect ON SEQUENCE s TO joe')->facts->diagnostics));
    }

    public function testPrivilegesLimitsDefaultsToTheKindItself(): void
    {
        self::assertSame(['invalid privilege type USAGE for relation'], array_map(static fn (\SqlSemantics\Statement\Fact\Diagnostic $diagnostic): string => $diagnostic->message(), (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER DEFAULT PRIVILEGES GRANT usage ON TABLES TO joe')->facts->diagnostics));
    }

    public function testColumnsReportsAKindWithoutColumns(): void
    {
        self::assertSame(['column privileges are only valid for relations'], array_map(static fn (\SqlSemantics\Statement\Fact\Diagnostic $diagnostic): string => $diagnostic->message(), (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('GRANT SELECT (a) ON SCHEMA s TO joe')->facts->diagnostics));
    }

    public function testColumnsReportsARightThatCannotBeLimited(): void
    {
        self::assertSame(['invalid privilege type DELETE for column'], array_map(static fn (\SqlSemantics\Statement\Fact\Diagnostic $diagnostic): string => $diagnostic->message(), (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('GRANT delete (a), update (a) ON t TO joe')->facts->diagnostics));
    }

    public function testNamedReportsAnUnknownName(): void
    {
        self::assertSame(['unrecognized privilege type "SELECT"'], array_map(static fn (\SqlSemantics\Statement\Fact\Diagnostic $diagnostic): string => $diagnostic->message(), (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('GRANT "SELECT" ON t TO joe')->facts->diagnostics));
    }

    public function testKnownMaintainFromRelease17(): void
    {
        self::assertSame(['unrecognized privilege type "maintain"'], array_map(static fn (\SqlSemantics\Statement\Fact\Diagnostic $diagnostic): string => $diagnostic->message(), (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql, 'pg-16.6'))->analyze('GRANT maintain ON t TO joe')->facts->diagnostics));
    }

    public function testGrantOptionReportsPublic(): void
    {
        self::assertSame(['grant options can only be granted to roles'], array_map(static fn (\SqlSemantics\Statement\Fact\Diagnostic $diagnostic): string => $diagnostic->message(), (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('GRANT SELECT ON t TO joe, PUBLIC WITH GRANT OPTION')->facts->diagnostics));
    }
}
