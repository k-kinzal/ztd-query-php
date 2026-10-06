<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Operator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Operator\CreateOperatorClass;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\NamedDesignation;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(CreateOperatorClass::class)]
#[Medium]
final class CreateOperatorClassTest extends TestCase
{
    public function testDeriveStatementRecordsNothingForAPlainClass(): void
    {
        $operation = (new Semantics(Dialect::PostgreSql))->analyze('CREATE OPERATOR CLASS c FOR TYPE int4 USING btree AS STORAGE int4', []);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testRenderDropsRecheck(): void
    {
        $operation = (new Semantics(Dialect::PostgreSql))->analyze('CREATE OPERATOR CLASS c DEFAULT FOR TYPE int4 USING gist FAMILY f AS OPERATOR 1 < (int4, int4) FOR SEARCH RECHECK, FUNCTION 1 (int4) f(int4)');
        self::assertSame('CREATE OPERATOR CLASS c DEFAULT FOR TYPE int4 USING gist FAMILY f AS OPERATOR 1 < (int4, int4) FOR SEARCH, FUNCTION 1 (int4) f (int4)', $operation->toString());
    }

    public function testRejectsAClassWithoutItems(): void
    {
        $this->expectExceptionMessage('An operator class has at least one item.');
        new CreateOperatorClass(new DottedName([new Name('c')]), new TypeName(new NamedDesignation(new DottedName([new Name('int4')]))), new Name('btree'), []);
    }
}
