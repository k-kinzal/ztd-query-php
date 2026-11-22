<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Mutation\Insert;
use SqlSemantics\Platform\PostgreSql\Ir\Mutation\InsertRow;
use SqlSemantics\Platform\PostgreSql\Ir\Mutation\InsertValue;
use SqlSemantics\Platform\PostgreSql\Ir\TableSource;

#[CoversClass(Insert::class)]
#[Small]
final class InsertTest extends TestCase
{
    public function testToStringKeepsTargetColumnAndRowOrder(): void
    {
        $target = new TableSource(new Identifier('public'), new Identifier('t'), [new Identifier('b'), new Identifier('a')]);
        $row = new InsertRow([new InsertValue(null), new InsertValue(new IntegerLiteral('-2'))]);
        $value = new Insert($target, [$row, $row]);

        self::assertSame('INSERT INTO "public"."t" ("b", "a") VALUES (NULL, CAST(-2 AS pg_catalog.int4)), (NULL, CAST(-2 AS pg_catalog.int4))', $value->toString());
    }

    public function testProfileIdBeginsWithTheNativeGrammarRelease(): void
    {
        $target = new TableSource(new Identifier('public'), new Identifier('t'), [new Identifier('a')]);
        $row = new InsertRow([new InsertValue(null)]);

        self::assertStringStartsWith('pg-17.2/', (new Insert($target, [$row]))->profileId());
    }

    public function testRejectsAWidthThatDoesNotMatchTheTarget(): void
    {
        $target = new TableSource(new Identifier('public'), new Identifier('t'), [new Identifier('b'), new Identifier('a')]);
        $row = new InsertRow([new InsertValue(null)]);
        $this->expectExceptionMessage('must match the target width');

        new Insert($target, [$row]);
    }
}
