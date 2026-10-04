<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Relation\Json;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Relation\Json\JsonExistsColumn::class)]
#[Small]
final class JsonExistsColumnTest extends TestCase
{
    public function testRenderWritesExistsAndThePath(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze("SELECT * FROM JSON_TABLE ('[]', '\$[*]' COLUMNS (e boolean EXISTS PATH '\$.e'))");
        self::assertSame("SELECT * FROM JSON_TABLE ('[]', '\$[*]' COLUMNS (e BOOLEAN EXISTS PATH '\$.e'))", $query->toString());
    }
}
