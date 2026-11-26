<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Privilege;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantPrivileges;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\UnknownGrantColumn;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(GrantPrivileges::class)]
#[Medium]
final class GrantPrivilegesTest extends TestCase
{
    public function testDeriveStatementResolvesTheTableAndItsColumns(): void
    {
        $semantics = new Inspection();
        $table = $semantics->diagnose('CREATE TABLE t (a INT)');
        $grant = $semantics->diagnose('GRANT SELECT (a, b) ON t TO u', [$table]);

        self::assertInstanceOf(GrantPrivileges::class, $grant->statement);
        self::assertInstanceOf(DeclaredTable::class, $grant->facts->relation($grant->statement->level)->table);
        self::assertInstanceOf(UnknownGrantColumn::class, $grant->facts->diagnostics[0]);
    }

    public function testRenderWritesEveryClause(): void
    {
        self::assertSame("GRANT SELECT ON FUNCTION db.f TO u IDENTIFIED BY 'x' REQUIRE NONE WITH MAX_USER_CONNECTIONS 2", (new Inspection('mysql-5.7.44'))->diagnose("grant select on function db.f to u identified by 'x' require none with max_user_connections 2")->toString());
    }
}
