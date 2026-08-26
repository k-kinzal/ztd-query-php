<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\FakeConnectionProperties;

#[CoversNothing]
final class ConnectionPropertiesTest extends TestCase
{
    public function testNamedAnswersWhatTheConnectionHoldsUnderThatName(): void
    {
        $properties = new FakeConnectionProperties(['errno' => 1146]);

        self::assertSame(1146, $properties->named('errno'));
    }

    public function testNamedAnswersNothingWhereTheConnectionHasNoSuchProperty(): void
    {
        self::assertNull((new FakeConnectionProperties())->named('no_such_property'));
    }
}
