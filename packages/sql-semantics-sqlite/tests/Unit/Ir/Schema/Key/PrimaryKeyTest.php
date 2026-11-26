<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Key;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Key\KeyConflict;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Key\KeyTerm;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Key\PrimaryKey;

#[CoversClass(PrimaryKey::class)]
#[Small]
final class PrimaryKeyTest extends TestCase
{
    public function testOwnsAnOrderedCopyOfTheTerms(): void
    {
        $first = new KeyTerm(2);
        $key = new PrimaryKey([&$first, new KeyTerm(0)], conflict: KeyConflict::Ignore);
        $first = new KeyTerm(3);

        self::assertSame([2, 0], array_column($key->terms, 'slot'));
        self::assertSame(KeyConflict::Ignore, $key->conflict);
    }
}
