<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Access\Role;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\CreateRole::class)]
#[Medium]
final class CreateRoleTest extends TestCase
{
    public function testRenderDropsTheNoiseWordWith(): void
    {
        self::assertSame('CREATE USER joe login PASSWORD \'s\'', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE USER joe WITH LOGIN ENCRYPTED PASSWORD \'s\'')->toString());
    }

    public function testRenderKeepsTheGroupWord(): void
    {
        self::assertSame('CREATE GROUP "Staff"', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE GROUP "Staff"')->toString());
    }

    public function testAnalysisKeepsTheOptionsInOrder(): void
    {
        $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE ROLE joe NOLOGIN CONNECTION LIMIT 3')->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\CreateRole::class, $statement);
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Object\RoleWord::Role, $statement->word);
        self::assertSame(['canlogin', 'connectionlimit'], [$statement->options[0]->option(), $statement->options[1]->option()]);
    }

    public function testDeriveStatementReportsAReservedName(): void
    {
        self::assertSame(['role name "pg_mine" is reserved'], array_map(static fn (\SqlSemantics\Statement\Fact\Diagnostic $diagnostic): string => $diagnostic->message(), (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE ROLE pg_mine')->facts->diagnostics));
    }

    public function testDeriveStatementReportsTheOptionProblems(): void
    {
        self::assertSame(['unrecognized role option "foo"', 'UNENCRYPTED PASSWORD is no longer supported', 'invalid connection limit: -2', 'role "public" does not exist', 'conflicting or redundant options'], array_map(static fn (\SqlSemantics\Statement\Fact\Diagnostic $diagnostic): string => $diagnostic->message(), (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE ROLE joe foo UNENCRYPTED PASSWORD \'x\' CONNECTION LIMIT -2 LOGIN NOLOGIN ROLE public')->facts->diagnostics));
    }

    public function testDeriveStatementAcceptsRepeatedSysid(): void
    {
        self::assertSame([], array_map(static fn (\SqlSemantics\Statement\Fact\Diagnostic $diagnostic): string => $diagnostic->message(), (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE ROLE joe SYSID 1 SYSID 2 SUPERUSER')->facts->diagnostics));
    }
}
