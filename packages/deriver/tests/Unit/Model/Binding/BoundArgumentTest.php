<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Binding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Model\Binding\BoundArgument
 */
#[CoversClass(\Deriver\Model\Binding\BoundArgument::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class BoundArgumentTest extends TestCase
{
    public function testMetadataDistinguishesOmittedAndEvaluatedValues(): void
    {
        $binding = new \Deriver\Model\Binding\BoundArgument('name', \Deriver\Value\Term::constant('default'), supplied: false);
        self::assertFalse($binding->supplied);
        self::assertSame('default', $binding->value->native());
        self::assertNull($binding->location);
    }
}
