<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Rules\Table\KeyClauses::class)]
#[Medium]
final class KeyClausesTest extends TestCase
{
    public function testDeriveChecksTheColumns(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $context = [];
        array_push($context, ...$semantics->analyze('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->analyze('CREATE TABLE n (a int, PRIMARY KEY (zz) INCLUDE (yy))', $context);
        self::assertSame([
          0 => 'column "zz" named in key does not exist',
          1 => 'column "yy" named in key does not exist',
        ], array_map(static fn ($problem): string => $problem->message(), $statement->facts->diagnostics));
    }

    public function testWriteWritesTheColumnsAndParameters(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE n (a int, b int, UNIQUE (a) INCLUDE (b) WITH (fillfactor = 70))', []);
        self::assertSame('CREATE TABLE n (a INT, b INT, UNIQUE (a) INCLUDE (b) WITH (fillfactor = 70))', $statement->toString());
    }
}
