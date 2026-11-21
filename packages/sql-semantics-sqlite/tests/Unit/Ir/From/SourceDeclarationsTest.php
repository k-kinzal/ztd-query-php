<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\From;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\From\SourceDeclarations;
use SqlSemantics\Platform\Sqlite\Semantics;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;

#[CoversClass(SourceDeclarations::class)]
#[Small]
final class SourceDeclarationsTest extends TestCase
{
    public function testBindKeepsTwoOccurrencesWithOneExternalDeclaration(): void
    {
        $sqlite = new Semantics();
        $table = $sqlite->inspect('CREATE TABLE t (a INT)');
        $operation = $sqlite->inspect('SELECT x.a FROM t x INNER JOIN t y ON x.a = y.a', [$table]);
        self::assertInstanceOf(Select::class, $operation->statement);

        $bindings = (new SourceDeclarations())->bind($operation, $operation->statement->from);
        self::assertCount(2, $bindings);
        self::assertNotSame($bindings[0][0], $bindings[1][0]);
        self::assertSame($bindings[0][1], $bindings[1][1]);
    }
}
