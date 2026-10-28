<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Rendering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Rendering\OptionalCommas;

#[CoversClass(OptionalCommas::class)]
#[Medium]
final class OptionalCommasTest extends TestCase
{
    public function testCheckedRetainsExactlyOneChoicePerBoundary(): void
    {
        self::assertSame([true, false, true], (new OptionalCommas())->checked([true, false, true], 4));
        self::assertSame([true, true], (new OptionalCommas())->checked(null, 3, true));
        self::assertSame([], (new OptionalCommas())->checked(null, 0));
    }

    public function testCheckedRejectsAListWithMissingBoundaries(): void
    {
        $this->expectExceptionMessage('Optional commas specify exactly the boundaries between list items.');
        (new OptionalCommas())->checked([true], 3);
    }

    public function testCheckedRejectsNonBooleanBoundaries(): void
    {
        $this->expectExceptionMessage('Each optional comma is a boolean choice.');
        (new OptionalCommas())->checked([1], 2);
    }

    public function testCheckedRejectsAssociativeBoundaries(): void
    {
        $this->expectExceptionMessage('Optional commas specify exactly the boundaries between list items.');
        (new OptionalCommas())->checked(['comma' => true], 2);
    }

    public function testRenderWritesOnlyTheChosenCommas(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze('ALTER RESOURCE GROUP rg VCPU 1, 2 3');

        self::assertSame('ALTER RESOURCE GROUP rg VCPU 1, 2 3', $statement->toString());
    }
}
