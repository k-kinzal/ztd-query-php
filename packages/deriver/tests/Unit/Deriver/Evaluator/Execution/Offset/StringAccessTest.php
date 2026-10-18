<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Execution\Offset;

use Deriver\Deriver\Evaluator\Execution\Memory\Location;
use Deriver\Deriver\Evaluator\Execution\Offset\StringAccess;
use Deriver\Deriver\Evaluator\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Deriver\Evaluator\Execution\Offset\StringAccess
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
