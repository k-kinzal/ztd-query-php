<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Binding;

use Deriver\Model\Binding\ArgumentBindings;
use Deriver\Model\Signature\Parameter;
use Deriver\Model\Signature\Signature;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Model\Binding\ArgumentBindings
 */
#[CoversClass(ArgumentBindings::class)]
#[UsesClass(\Deriver\Model\Binding\BoundArgument::class)]
#[UsesClass(\Deriver\Model\Binding\LocationRef::class)]
#[UsesClass(Parameter::class)]
#[UsesClass(Signature::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class ArgumentBindingsTest extends TestCase
{
    public function testFormalRetainsNamesVariadicsAndReferenceLocations(): void
    {
        $bindings = ArgumentBindings::formal(new Signature([new Parameter('values', byReference: true, variadic: true)]));
        self::assertFalse($bindings->evaluated);
        self::assertNull($bindings->arguments['values']->supplied);
        self::assertSame('values', $bindings->arguments['values']->location?->name);
        self::assertSame('array', $bindings->arguments['values']->value->attributes['type']);
    }
}
