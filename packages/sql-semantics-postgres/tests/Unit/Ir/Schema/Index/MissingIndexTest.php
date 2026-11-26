<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\MissingIndex;

#[CoversClass(MissingIndex::class)]
#[Small]
final class MissingIndexTest extends TestCase
{
    public function testQuotedSpellsTheOptionalOriginalSchema(): void
    {
        $name = new Identifier('i.d');
        $bare = new MissingIndex($name);
        $qualified = new MissingIndex($name, new Identifier('vacant'), true);

        self::assertSame('"i.d"', $bare->quoted());
        self::assertFalse($bare->missingSchema);
        self::assertSame('"vacant"."i.d"', $qualified->quoted());
        self::assertTrue($qualified->missingSchema);
    }
}
