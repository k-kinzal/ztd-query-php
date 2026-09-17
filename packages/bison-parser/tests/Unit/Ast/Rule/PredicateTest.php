<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Rule;

use BisonParser\Ast\Location;
use BisonParser\Ast\Rule\Predicate;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Predicate::class)]
#[UsesClass(Location::class)]
#[Small]
final class PredicateTest extends TestCase
{
    public function testLocation(): void
    {
        $item = new Predicate(' new_syntax ', new Location(5, 9));

        self::assertSame('5:9', (string) $item->location());
        self::assertSame(' new_syntax ', $item->code);
    }
}
