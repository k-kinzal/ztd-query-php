<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Binding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Model\Binding\ArgumentBindings
 */
#[CoversClass(\Deriver\Model\Binding\ArgumentBindings::class)]
#[UsesClass(\Deriver\Model\Binding\BoundArgument::class)]
#[UsesClass(\Deriver\Model\Binding\LocationRef::class)]
#[UsesClass(\Deriver\Model\Signature\Parameter::class)]
#[UsesClass(\Deriver\Model\Signature\Signature::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class ArgumentBindingsTest extends TestCase
{
    public function testFormalRetainsNamesVariadicsAndReferenceLocations(): void
    {
        $bindings = \Deriver\Model\Binding\ArgumentBindings::formal(new \Deriver\Model\Signature\Signature([new \Deriver\Model\Signature\Parameter('values', byReference: true, variadic: true)]));
        self::assertFalse($bindings->evaluated);
        self::assertNull($bindings->arguments['values']->supplied);
        self::assertSame('values', $bindings->arguments['values']->location?->name);
        self::assertSame('array', $bindings->arguments['values']->value->attributes['type']);
    }
}
