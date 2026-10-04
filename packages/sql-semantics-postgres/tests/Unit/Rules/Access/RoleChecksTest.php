<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Access;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Rules\Access\RoleChecks::class)]
#[Medium]
final class RoleChecksTest extends TestCase
{
    public function testOptionsReportsEveryProblemOnce(): void
    {
        self::assertSame(['unrecognized role option "bar"', 'invalid connection limit: -3', 'conflicting or redundant options'], array_map(static fn (\SqlSemantics\Statement\Fact\Diagnostic $diagnostic): string => $diagnostic->message(), (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER ROLE joe bar CONNECTION LIMIT -3 CONNECTION LIMIT 2 INHERIT noinherit')->facts->diagnostics));
    }

    public function testOptionsAcceptsDistinctOptions(): void
    {
        self::assertSame([], array_map(static fn (\SqlSemantics\Statement\Fact\Diagnostic $diagnostic): string => $diagnostic->message(), (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE ROLE joe SUPERUSER CREATEDB CREATEROLE INHERIT LOGIN REPLICATION BYPASSRLS CONNECTION LIMIT -1 PASSWORD NULL VALID UNTIL \'infinity\' IN ROLE a ROLE b ADMIN c')->facts->diagnostics));
    }

    public function testReservedReportsThePrefix(): void
    {
        self::assertSame(['role name "pg_x" is reserved'], array_map(static fn (\SqlSemantics\Statement\Fact\Diagnostic $diagnostic): string => $diagnostic->message(), (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE ROLE pg_x')->facts->diagnostics));
    }

    public function testAlterableReportsPublicAndReservedNames(): void
    {
        self::assertSame(['role name "pg_x" is reserved'], array_map(static fn (\SqlSemantics\Statement\Fact\Diagnostic $diagnostic): string => $diagnostic->message(), (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER ROLE pg_x LOGIN')->facts->diagnostics));
    }

    public function testExistingReportsPublic(): void
    {
        self::assertSame(['role "public" does not exist'], array_map(static fn (\SqlSemantics\Statement\Fact\Diagnostic $diagnostic): string => $diagnostic->message(), (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('DROP OWNED BY PUBLIC')->facts->diagnostics));
    }

    public function testExistingAcceptsSessionDesignations(): void
    {
        self::assertSame([], array_map(static fn (\SqlSemantics\Statement\Fact\Diagnostic $diagnostic): string => $diagnostic->message(), (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('DROP OWNED BY CURRENT_USER, SESSION_USER, CURRENT_ROLE')->facts->diagnostics));
    }

    public function testDroppableReportsADesignation(): void
    {
        self::assertSame(['cannot use special role specifier in DROP ROLE'], array_map(static fn (\SqlSemantics\Statement\Fact\Diagnostic $diagnostic): string => $diagnostic->message(), (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('DROP ROLE SESSION_USER')->facts->diagnostics));
    }
}
