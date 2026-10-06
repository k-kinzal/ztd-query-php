<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Privilege;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantPrivileges;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\UnknownGrantColumn;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;

#[CoversClass(GrantPrivileges::class)]
#[Medium]
final class GrantPrivilegesTest extends TestCase
{
    public function testDeriveStatementResolvesTheTableAndItsColumns(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT)');
        $grant = $semantics->analyze('GRANT SELECT (a, b) ON t TO u', [$table]);

        self::assertInstanceOf(GrantPrivileges::class, $grant->statement);
        self::assertInstanceOf(DeclaredTable::class, $grant->facts->relation($grant->statement->level)->table);
        self::assertInstanceOf(UnknownGrantColumn::class, $grant->facts->diagnostics[0]);
    }

    public function testRenderWritesEveryClause(): void
    {
        self::assertSame("GRANT SELECT ON FUNCTION db.f TO u IDENTIFIED BY 'x' REQUIRE NONE WITH MAX_USER_CONNECTIONS 2", (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze("grant select on function db.f to u identified by 'x' require none with max_user_connections 2")->toString());
    }
}
