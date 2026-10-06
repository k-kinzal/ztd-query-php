<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Offset;

use Deriver\Evaluation\Offset\StringAccess;
use Deriver\Memory\Location;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Evaluation\Offset\StringAccess
 */
#[CoversClass(StringAccess::class)]
#[UsesClass(Location::class)]
#[UsesClass(Term::class)]
#[Small]
final class StringAccessTest extends TestCase
{
    public function testRetainsCompleteStringStorage(): void
    {
        $location = new Location('root', ['text']);
        $access = new StringAccess($location, Term::constant(-1));
        self::assertSame($location, $access->container);
        self::assertSame(-1, $access->key?->native());
    }
}
