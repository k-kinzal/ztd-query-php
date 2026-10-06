<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Access\Defaults;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Defaults\AlterDefaultPrivileges::class)]
#[Medium]
final class AlterDefaultPrivilegesTest extends TestCase
{
    public function testRenderWritesTheClausesAndTheAction(): void
    {
        self::assertSame('ALTER DEFAULT PRIVILEGES FOR ROLE a IN SCHEMA s GRANT SELECT ON TABLES TO joe', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER DEFAULT PRIVILEGES FOR ROLE a IN SCHEMA s GRANT SELECT ON TABLES TO joe')->toString());
    }

    public function testDeriveStatementReportsTheProblems(): void
    {
        self::assertSame(['cannot use IN SCHEMA clause when using GRANT/REVOKE ON SCHEMAS', 'cannot use IN SCHEMA clause when using GRANT/REVOKE ON SCHEMAS', 'role "public" does not exist', 'conflicting or redundant options', 'default privileges cannot be set for columns', 'unrecognized privilege type "foo"', 'invalid privilege type EXECUTE for schema', 'grant options can only be granted to roles'], array_map(static fn (\SqlSemantics\Statement\Fact\Diagnostic $diagnostic): string => $diagnostic->message(), (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER DEFAULT PRIVILEGES IN SCHEMA a IN SCHEMA b FOR ROLE PUBLIC GRANT SELECT (c), foo, execute ON SCHEMAS TO PUBLIC WITH GRANT OPTION')->facts->diagnostics));
    }

    public function testDeriveStatementAcceptsAValidRequest(): void
    {
        self::assertSame([], array_map(static fn (\SqlSemantics\Statement\Fact\Diagnostic $diagnostic): string => $diagnostic->message(), (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER DEFAULT PRIVILEGES FOR ROLE a IN SCHEMA s REVOKE EXECUTE ON FUNCTIONS FROM PUBLIC')->facts->diagnostics));
    }
}
