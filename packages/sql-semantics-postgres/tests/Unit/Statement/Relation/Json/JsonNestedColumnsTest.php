<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Relation\Json;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Relation\Json\JsonNestedColumns::class)]
#[Small]
final class JsonNestedColumnsTest extends TestCase
{
    public function testRenderWritesNestedPath(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze("SELECT * FROM JSON_TABLE ('[]', '\$[*]' COLUMNS (NESTED '\$.b' AS q COLUMNS (b text)))");
        self::assertSame("SELECT * FROM JSON_TABLE ('[]', '\$[*]' COLUMNS (NESTED PATH '\$.b' AS q COLUMNS (b text)))", $query->toString());
    }
}
