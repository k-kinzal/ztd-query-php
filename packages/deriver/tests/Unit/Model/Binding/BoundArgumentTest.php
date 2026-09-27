<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Binding;

use Deriver\Model\Binding\BoundArgument;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Model\Binding\BoundArgument
 */
#[CoversClass(BoundArgument::class)]
#[UsesClass(Term::class)]
#[Small]
final class BoundArgumentTest extends TestCase
{
    public function testMetadataDistinguishesOmittedAndEvaluatedValues(): void
    {
        $binding = new BoundArgument('name', Term::constant('default'), supplied: false);
        self::assertFalse($binding->supplied);
        self::assertSame('default', $binding->value->native());
        self::assertNull($binding->location);
    }
}
