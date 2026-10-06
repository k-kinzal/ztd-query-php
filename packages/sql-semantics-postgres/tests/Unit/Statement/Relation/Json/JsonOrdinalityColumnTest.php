<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Relation\Json;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Relation\Json\JsonOrdinalityColumn::class)]
#[Medium]
final class JsonOrdinalityColumnTest extends TestCase
{
    public function testRenderWritesForOrdinality(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze("SELECT * FROM JSON_TABLE ('[]', '\$[*]' COLUMNS (n FOR ORDINALITY))");
        self::assertSame("SELECT * FROM JSON_TABLE ('[]', '\$[*]' COLUMNS (n FOR ORDINALITY))", $query->toString());
    }
}
