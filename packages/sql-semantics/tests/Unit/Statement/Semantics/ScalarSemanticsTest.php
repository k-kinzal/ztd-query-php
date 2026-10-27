<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Semantics;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Statement\Semantics\ScalarSemantics::class)]
#[Small]
final class ScalarSemanticsTest extends TestCase
{
    public function testInitializeScalarInformationRetainsOneBinding(): void
    {
        $node = new class () {
            use \SqlSemantics\Statement\Semantics\ScalarSemantics;
        };
        $information = new \SqlSemantics\Statement\Fact\ScalarFact(new \SqlSemantics\Statement\Type\Known(\SqlSemantics\Platform\Sqlite\Statement\Type\Storage::Integer), \SqlSemantics\Statement\Type\Nullability::NotNull);
        $node->initializeScalarInformation($information);
        $node->initializeScalarInformation($information);
        self::assertSame($information, $node->scalarInformation());
        $this->expectExceptionMessage('A scalar occurrence is resolved once.');
        $node->initializeScalarInformation(new \SqlSemantics\Statement\Fact\ScalarFact(new \SqlSemantics\Statement\Type\Known(\SqlSemantics\Platform\Sqlite\Statement\Type\Storage::Integer), \SqlSemantics\Statement\Type\Nullability::NotNull));
    }

    public function testScalarInformationRequiresCompletedConstruction(): void
    {
        $node = new class () {
            use \SqlSemantics\Statement\Semantics\ScalarSemantics;
        };
        $this->expectExceptionMessage('A scalar occurrence must be resolved before use.');
        $node->scalarInformation();
    }

    public function testHasScalarInformationChangesWhenTheBindingIsSupplied(): void
    {
        $node = new class () {
            use \SqlSemantics\Statement\Semantics\ScalarSemantics;
        };
        self::assertFalse($node->hasScalarInformation());
        $node->initializeScalarInformation(new \SqlSemantics\Statement\Fact\ScalarFact(new \SqlSemantics\Statement\Type\Known(\SqlSemantics\Platform\Sqlite\Statement\Type\Storage::Integer), \SqlSemantics\Statement\Type\Nullability::NotNull));
        self::assertTrue($node->hasScalarInformation());
    }

    public function testDataTypeReturnsTheBoundType(): void
    {
        $node = new class () {
            use \SqlSemantics\Statement\Semantics\ScalarSemantics;
        };
        $information = new \SqlSemantics\Statement\Fact\ScalarFact(new \SqlSemantics\Statement\Type\Known(\SqlSemantics\Platform\Sqlite\Statement\Type\Storage::Integer), \SqlSemantics\Statement\Type\Nullability::NotNull);
        $node->initializeScalarInformation($information);
        self::assertSame($information->type, $node->dataType());
    }

    public function testNullabilityReturnsTheBoundNullability(): void
    {
        $node = new class () {
            use \SqlSemantics\Statement\Semantics\ScalarSemantics;
        };
        $information = new \SqlSemantics\Statement\Fact\ScalarFact(new \SqlSemantics\Statement\Type\Known(\SqlSemantics\Platform\Sqlite\Statement\Type\Storage::Integer), \SqlSemantics\Statement\Type\Nullability::NotNull);
        $node->initializeScalarInformation($information);
        self::assertSame($information->nullability, $node->nullability());
    }
}
