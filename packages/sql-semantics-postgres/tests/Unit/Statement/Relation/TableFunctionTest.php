<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Relation\TableFunction::class)]
#[Small]
final class TableFunctionTest extends TestCase
{
    public function testRenderWritesTheDefinitions(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze('SELECT * FROM ROWS FROM (f() AS (a integer, b text), g())');
        self::assertSame('SELECT * FROM ROWS FROM (f() AS (a INTEGER, b text), g())', $query->toString());
    }
}
