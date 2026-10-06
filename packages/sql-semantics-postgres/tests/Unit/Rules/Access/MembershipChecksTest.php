<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Access;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Rules\Access\MembershipChecks::class)]
#[Medium]
final class MembershipChecksTest extends TestCase
{
    public function testRolesReportsColumnsAndPublic(): void
    {
        self::assertSame(['column names cannot be included in GRANT/REVOKE ROLE', 'role "public" does not exist'], array_map(static fn (\SqlSemantics\Statement\Fact\Diagnostic $diagnostic): string => $diagnostic->message(), (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('GRANT a (c) TO PUBLIC')->facts->diagnostics));
    }

    public function testOptionReportsTheName(): void
    {
        self::assertSame(['unrecognized role option "Admin"'], array_map(static fn (\SqlSemantics\Statement\Fact\Diagnostic $diagnostic): string => $diagnostic->message(), (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('GRANT a TO b WITH "Admin" TRUE')->facts->diagnostics));
    }
}
