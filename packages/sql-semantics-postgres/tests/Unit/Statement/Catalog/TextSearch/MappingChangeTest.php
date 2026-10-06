<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\TextSearch;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\TextSearch\MappingChange::class)]
#[Medium]
final class MappingChangeTest extends TestCase
{
    public function testAlterIsSpelledAlter(): void
    {
        self::assertSame('ALTER TEXT SEARCH CONFIGURATION c ALTER MAPPING FOR word WITH simple', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER TEXT SEARCH CONFIGURATION c ALTER MAPPING FOR word WITH simple')->toString());
    }
}
