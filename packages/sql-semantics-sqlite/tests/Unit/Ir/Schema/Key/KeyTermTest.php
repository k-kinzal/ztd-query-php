<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Key;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Collation;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Key\KeyTerm;

#[CoversClass(KeyTerm::class)]
#[Small]
final class KeyTermTest extends TestCase
{
    public function testKeepsKeyPositionDirectionAndCollation(): void
    {
        $term = new KeyTerm(3, true, Collation::RightTrim);

        self::assertSame(3, $term->slot);
        self::assertTrue($term->descending);
        self::assertSame(Collation::RightTrim, $term->collation);
    }
}
