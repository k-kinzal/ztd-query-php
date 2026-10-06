<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Operator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Operator\OperatorFamilyRemoval;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(OperatorFamilyRemoval::class)]
#[Medium]
final class OperatorFamilyRemovalTest extends TestCase
{
    public function testDeriveStatementRecordsNothing(): void
    {
        $operation = (new Semantics(Dialect::PostgreSql))->analyze('ALTER OPERATOR FAMILY f USING btree DROP OPERATOR 1 (int4, int8)', []);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testRenderWritesTheMembers(): void
    {
        $operation = (new Semantics(Dialect::PostgreSql))->analyze('ALTER OPERATOR FAMILY f USING btree DROP OPERATOR 1 (int4, int8), FUNCTION 2 (int4)');
        self::assertSame('ALTER OPERATOR FAMILY f USING btree DROP OPERATOR 1 (int4, int8), FUNCTION 2 (int4)', $operation->toString());
    }

    public function testRejectsNoMember(): void
    {
        $this->expectExceptionMessage('DROP names at least one member.');
        new OperatorFamilyRemoval(new DottedName([new Name('f')]), new Name('btree'), []);
    }
}
