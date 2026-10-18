<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Call;

use Deriver\Deriver\Evaluator\Call\ArgumentBindings;
use Deriver\Deriver\Evaluator\Declaration\Parameter;
use Deriver\Deriver\Evaluator\Declaration\Signature;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Deriver\Evaluator\Call\ArgumentBindings
 */
#[CoversClass(ArgumentBindings::class)]
#[UsesClass(\Deriver\Deriver\Evaluator\Call\BoundArgument::class)]
#[UsesClass(\Deriver\Deriver\Evaluator\Call\Plan\Location\LocationRef::class)]
#[UsesClass(Parameter::class)]
#[UsesClass(Signature::class)]
#[UsesClass(\Deriver\Deriver\Evaluator\Term::class)]
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
