<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Access;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Rules\Access\DefaultPrivilegeChecks::class)]
#[Medium]
final class DefaultPrivilegeChecksTest extends TestCase
{
    public function testCheckReportsARepeatedClause(): void
    {
        self::assertSame(['conflicting or redundant options'], array_map(static fn (\SqlSemantics\Statement\Fact\Diagnostic $diagnostic): string => $diagnostic->message(), (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER DEFAULT PRIVILEGES FOR ROLE a FOR USER b GRANT SELECT ON TABLES TO joe')->facts->diagnostics));
    }

    public function testCheckAcceptsDistinctClauses(): void
    {
        self::assertSame([], array_map(static fn (\SqlSemantics\Statement\Fact\Diagnostic $diagnostic): string => $diagnostic->message(), (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER DEFAULT PRIVILEGES FOR ROLE a IN SCHEMA s GRANT usage ON TYPES TO joe WITH GRANT OPTION')->facts->diagnostics));
    }
}
