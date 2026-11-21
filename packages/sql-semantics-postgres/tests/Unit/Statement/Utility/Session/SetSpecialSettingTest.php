<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\SetSpecialSetting::class)]
#[Medium]
final class SetSpecialSettingTest extends TestCase
{
    public function testDeriveStatementReportsSetCatalog(): void
    {
        self::assertSame('current database cannot be changed', (new \SqlSemantics\Platform\PostgreSql\Semantics())->diagnose('SET CATALOG \'d\'')->facts->diagnostics[0]->message());
    }

    public function testDeriveStatementAcceptsSetSchema(): void
    {
        self::assertSame([], (new \SqlSemantics\Platform\PostgreSql\Semantics())->diagnose('SET SCHEMA \'app\'')->facts->diagnostics);
    }

    public function testRenderWritesEachSetting(): void
    {
        self::assertSame(["SET SCHEMA 'a'", 'SET NAMES', 'SET NAMES DEFAULT', "SET LOCAL NAMES 'utf8'", 'SET ROLE none', "SET SESSION AUTHORIZATION 'u'", 'SET SESSION AUTHORIZATION DEFAULT', 'SET XML OPTION CONTENT', "SET TRANSACTION SNAPSHOT 's'"], [(new \SqlSemantics\Platform\PostgreSql\Semantics())->diagnose('SET SCHEMA \'a\'')->toString(), (new \SqlSemantics\Platform\PostgreSql\Semantics())->diagnose('SET NAMES')->toString(), (new \SqlSemantics\Platform\PostgreSql\Semantics())->diagnose('SET NAMES DEFAULT')->toString(), (new \SqlSemantics\Platform\PostgreSql\Semantics())->diagnose('SET LOCAL NAMES \'utf8\'')->toString(), (new \SqlSemantics\Platform\PostgreSql\Semantics())->diagnose('SET ROLE NONE')->toString(), (new \SqlSemantics\Platform\PostgreSql\Semantics())->diagnose('SET SESSION AUTHORIZATION \'u\'')->toString(), (new \SqlSemantics\Platform\PostgreSql\Semantics())->diagnose('SET SESSION SESSION AUTHORIZATION DEFAULT')->toString(), (new \SqlSemantics\Platform\PostgreSql\Semantics())->diagnose('SET XML OPTION CONTENT')->toString(), (new \SqlSemantics\Platform\PostgreSql\Semantics())->diagnose('SET TRANSACTION SNAPSHOT \'s\'')->toString()]);
    }
}
