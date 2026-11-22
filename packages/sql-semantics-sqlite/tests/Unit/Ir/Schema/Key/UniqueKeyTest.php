<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Key;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Key\KeyConflict;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Key\KeyTerm;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Key\UniqueKey;

#[CoversClass(UniqueKey::class)]
#[Small]
final class UniqueKeyTest extends TestCase
{
    public function testOwnsOrderedTermsAndTheExplicitConflictPolicy(): void
    {
        $first = new KeyTerm(2);
        $key = new UniqueKey([&$first, new KeyTerm(0)], conflict: KeyConflict::Abort);
        $first = new KeyTerm(1);

        self::assertSame([2, 0], array_column($key->terms, 'slot'));
        self::assertSame(KeyConflict::Abort, $key->conflict);
    }
}
