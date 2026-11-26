<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Access\Privilege;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\Revoke::class)]
#[Medium]
final class RevokeTest extends TestCase
{
    public function testRenderWritesEveryClause(): void
    {
        self::assertSame('REVOKE GRANT OPTION FOR SELECT, usage ON SEQUENCE s.q FROM joe GRANTED BY CURRENT_ROLE CASCADE', (new \SqlSemantics\Testing\PostgreSql\Inspection())->diagnose('REVOKE GRANT OPTION FOR SELECT, usage ON SEQUENCE s.q FROM GROUP joe GRANTED BY CURRENT_ROLE CASCADE')->toString());
    }

    public function testRenderWritesAll(): void
    {
        self::assertSame('REVOKE ALL ON DATABASE d FROM public RESTRICT', (new \SqlSemantics\Testing\PostgreSql\Inspection())->diagnose('REVOKE ALL PRIVILEGES ON DATABASE d FROM PUBLIC RESTRICT')->toString());
    }

    public function testAnalysisKeepsTheBehavior(): void
    {
        $statement = (new \SqlSemantics\Testing\PostgreSql\Inspection())->diagnose('REVOKE CREATE ON TABLESPACE ts FROM joe RESTRICT')->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\Revoke::class, $statement);
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Option\DropBehavior::Restrict, $statement->behavior);
        self::assertFalse($statement->grantOptionOnly);
    }

    public function testDeriveStatementReportsTheProblems(): void
    {
        self::assertSame(['invalid privilege type CONNECT for tablespace', 'role "public" does not exist'], array_map(static fn (\SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\Diagnostic $diagnostic): string => $diagnostic->message(), (new \SqlSemantics\Testing\PostgreSql\Inspection())->diagnose('REVOKE connect ON TABLESPACE ts FROM joe GRANTED BY PUBLIC')->facts->diagnostics));
    }

    public function testRejectsNoRole(): void
    {
        $this->expectExceptionMessage('A revoke names at least one role.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\Revoke([], new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\NamedObjectsTarget(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\PrivilegeObjectKind::Schema, [new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('s')]), []);
    }
}
