<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Access\Privilege;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\Grant::class)]
#[Medium]
final class GrantTest extends TestCase
{
    public function testRenderDropsTheNoiseWords(): void
    {
        self::assertSame('GRANT ALL ON TABLE accounts TO staff WITH GRANT OPTION GRANTED BY boss', (new \SqlSemantics\Testing\PostgreSql\Inspection())->diagnose('GRANT ALL PRIVILEGES ON accounts TO GROUP staff WITH GRANT OPTION GRANTED BY boss')->toString());
    }

    public function testRenderWritesAllWithColumns(): void
    {
        self::assertSame('GRANT ALL (a, b) ON TABLE t TO public', (new \SqlSemantics\Testing\PostgreSql\Inspection())->diagnose('GRANT ALL PRIVILEGES (a, b) ON TABLE t TO PUBLIC')->toString());
    }

    public function testAnalysisKeepsAllPrivilegesAsAnEmptyList(): void
    {
        $statement = (new \SqlSemantics\Testing\PostgreSql\Inspection())->diagnose('GRANT ALL ON SCHEMA s TO joe')->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\Grant::class, $statement);
        self::assertSame([], $statement->privileges);
        self::assertFalse($statement->grantOption);
        self::assertNull($statement->grantor);
    }

    public function testDeriveStatementReportsTheProblems(): void
    {
        self::assertSame(['unrecognized privilege type "foo"', 'invalid privilege type EXECUTE for schema', 'grant options can only be granted to roles', 'role "public" does not exist'], array_map(static fn (\SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\Diagnostic $diagnostic): string => $diagnostic->message(), (new \SqlSemantics\Testing\PostgreSql\Inspection())->diagnose('GRANT foo, EXECUTE ON SCHEMA s TO PUBLIC WITH GRANT OPTION GRANTED BY PUBLIC')->facts->diagnostics));
    }

    public function testDeriveStatementKeepsAGrantOptionOnSchemaContentsOpen(): void
    {
        self::assertSame([], array_map(static fn (\SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\Diagnostic $diagnostic): string => $diagnostic->message(), (new \SqlSemantics\Testing\PostgreSql\Inspection())->diagnose('GRANT SELECT ON ALL TABLES IN SCHEMA s TO PUBLIC WITH GRANT OPTION')->facts->diagnostics));
    }

    public function testRejectsAllAmongOtherPrivileges(): void
    {
        $this->expectExceptionMessage('ALL with a column list is the only privilege of its list.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\Grant([new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\Privilege(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\PrivilegeKeyword::All, [new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('a')]), new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\Privilege(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\PrivilegeKeyword::Select)], new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\NamedObjectsTarget(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\PrivilegeObjectKind::Schema, [new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('s')]), [new \SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpec(\SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpecKind::Named, new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('joe'))]);
    }

    public function testRejectsNoGrantee(): void
    {
        $this->expectExceptionMessage('A grant names at least one grantee.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\Grant([], new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\NamedObjectsTarget(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Target\PrivilegeObjectKind::Schema, [new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('s')]), []);
    }
}
