<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Inspection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Query\Inspection\Session\VariableScope;
use SqlSemantics\Model\Statement\Inspection\Session\ShowDiagnosticsStatement;
use SqlSemantics\Model\Statement\Inspection\Session\ShowGrantsStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Inspection\SessionInspections;

#[CoversClass(SessionInspections::class)]
#[Medium]
final class SessionInspectionsTest extends TestCase
{
    #[TestWith(["SHOW SESSION STATUS LIKE 'a'", "SHOW STATUS LIKE 'a'"])]
    #[TestWith(['SHOW GLOBAL VARIABLES', 'SHOW GLOBAL VARIABLES'])]
    #[TestWith(['SHOW COUNT(*) WARNINGS', 'SHOW COUNT(*) WARNINGS'])]
    #[TestWith(['SHOW REPLICAS', 'SHOW REPLICAS'])]
    public function testWriteNormalizesSynonyms(string $sql, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind($sql);
        self::assertSame($expected, SessionInspections::write($statement)?->toString());
    }

    public function testWriteReturnsNullForOtherStatements(): void
    {
        self::assertNull(SessionInspections::write((new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')));
    }

    public function testScopeWritesOnlyGlobal(): void
    {
        self::assertSame([' GLOBAL', ''], [SessionInspections::scope(VariableScope::Global), SessionInspections::scope(VariableScope::Session)]);
    }

    public function testWindowWritesCountThenOffset(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW WARNINGS LIMIT 1, 2');
        self::assertInstanceOf(ShowDiagnosticsStatement::class, $statement);
        self::assertSame(['LIMIT', '2', 'OFFSET', '1'], array_map(static fn ($tree): string => $tree->toString(), SessionInspections::window($statement->limit)));
        self::assertSame([], SessionInspections::window(null));
    }

    public function testGrantsNamesTheAuthenticatedAccountOnlyWithRoles(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $plain = $binder->bind('SHOW GRANTS FOR CURRENT_USER');
        $roles = $binder->bind('SHOW GRANTS FOR CURRENT_USER() USING r');
        self::assertInstanceOf(ShowGrantsStatement::class, $plain);
        self::assertInstanceOf(ShowGrantsStatement::class, $roles);
        self::assertSame('SHOW GRANTS', SessionInspections::grants($plain)->toString());
        self::assertSame("SHOW GRANTS FOR CURRENT_USER USING 'r'", SessionInspections::grants($roles)->toString());
    }
}
