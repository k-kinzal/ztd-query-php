<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Key;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\Key\PrimaryKey;
use SqlSemantics\Platform\MySql\Ir\Schema\Key\PrimaryKeyColumn;

#[CoversClass(PrimaryKey::class)]
#[Small]
final class PrimaryKeyTest extends TestCase
{
    public function testOwnsTheOrderedPrimaryKeyTerms(): void
    {
        $last = new PrimaryKeyColumn(2);
        $next = new PrimaryKeyColumn(0, true);
        $terms = [$last, $next];
        $key = new PrimaryKey($terms);
        $terms[0] = new PrimaryKeyColumn(1);

        self::assertSame([$last, $next], $key->terms);
    }
}
