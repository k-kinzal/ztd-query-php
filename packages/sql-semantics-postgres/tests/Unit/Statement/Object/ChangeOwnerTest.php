<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Object;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpec;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpecKind;
use SqlSemantics\Platform\PostgreSql\Statement\Object\ChangeOwner;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\UnqualifiedName;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(ChangeOwner::class)]
#[Medium]
final class ChangeOwnerTest extends TestCase
{
    public function testDeriveStatementRecordsNothingForASchema(): void
    {
        $operation = (new Semantics(Dialect::PostgreSql))->analyze('ALTER SCHEMA s OWNER TO r', []);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testRenderWritesTheOwner(): void
    {
        $operation = (new Semantics(Dialect::PostgreSql))->analyze('ALTER LARGE OBJECT 12 OWNER TO SESSION_USER');
        self::assertSame('ALTER LARGE OBJECT 12 OWNER TO SESSION_USER', $operation->toString());
    }

    public function testRejectsAKindWithoutOwner(): void
    {
        $this->expectExceptionMessage('OWNER TO names the object as the grammar names objects of its kind.');
        new ChangeOwner(ObjectKind::Extension, new UnqualifiedName(new Name('e')), new RoleSpec(RoleSpecKind::CurrentUser));
    }
}
