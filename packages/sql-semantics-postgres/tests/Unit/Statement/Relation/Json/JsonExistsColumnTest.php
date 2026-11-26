<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Relation\Json;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Relation\Json\JsonExistsColumn::class)]
#[Medium]
final class JsonExistsColumnTest extends TestCase
{
    public function testRenderWritesExistsAndThePath(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $query = $semantics->inspect("SELECT * FROM JSON_TABLE ('[]', '\$[*]' COLUMNS (e boolean EXISTS PATH '\$.e'))");
        self::assertSame("SELECT * FROM JSON_TABLE ('[]', '\$[*]' COLUMNS (e BOOLEAN EXISTS PATH '\$.e'))", $query->toString());
    }

    public function testRenderQuotesAColumnNamedNested(): void
    {
        $semantics = new \SqlSemantics\Testing\PostgreSql\Inspection();
        $query = $semantics->inspect("SELECT * FROM JSON_TABLE ('[]', '\$[*]' COLUMNS (\"nested\" path EXISTS))");
        self::assertSame("SELECT * FROM JSON_TABLE ('[]', '\$[*]' COLUMNS (\"nested\" path EXISTS))", $query->toString());
    }
}
