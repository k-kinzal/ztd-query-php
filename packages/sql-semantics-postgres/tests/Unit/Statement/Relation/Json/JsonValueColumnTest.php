<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Relation\Json;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Relation\Json\JsonValueColumn::class)]
#[Small]
final class JsonValueColumnTest extends TestCase
{
    public function testRenderWritesTheClausesInOrder(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze("SELECT * FROM JSON_TABLE ('[]', '\$[*]' COLUMNS (f jsonb FORMAT JSON PATH '\$.f' WITH WRAPPER))");
        self::assertSame("SELECT * FROM JSON_TABLE ('[]', '\$[*]' COLUMNS (f jsonb FORMAT JSON PATH '\$.f' WITH WRAPPER))", $query->toString());
    }
}
