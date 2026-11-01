<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Semantics;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Statement\Semantics\RelationSemantics::class)]
#[Small]
final class RelationSemanticsTest extends TestCase
{
    public function testInitializeRelationInformationRetainsOneBinding(): void
    {
        $node = new class () {
            use \SqlSemantics\Statement\Semantics\RelationSemantics;
        };
        $information = new \SqlSemantics\Statement\Fact\RelationFact(new \SqlSemantics\Statement\Shape\RowShape([]));
        $node->initializeRelationInformation($information);
        $node->initializeRelationInformation($information);
        self::assertSame($information, $node->relationInformation());
        $this->expectExceptionMessage('A relation occurrence is resolved once.');
        $node->initializeRelationInformation(new \SqlSemantics\Statement\Fact\RelationFact(new \SqlSemantics\Statement\Shape\RowShape([])));
    }

    public function testRelationInformationRequiresCompletedConstruction(): void
    {
        $node = new class () {
            use \SqlSemantics\Statement\Semantics\RelationSemantics;
        };
        $this->expectExceptionMessage('A relation occurrence must be resolved before use.');
        $node->relationInformation();
    }

    public function testHasRelationInformationChangesWhenTheBindingIsSupplied(): void
    {
        $node = new class () {
            use \SqlSemantics\Statement\Semantics\RelationSemantics;
        };
        self::assertFalse($node->hasRelationInformation());
        $node->initializeRelationInformation(new \SqlSemantics\Statement\Fact\RelationFact(new \SqlSemantics\Statement\Shape\RowShape([])));
        self::assertTrue($node->hasRelationInformation());
    }
}
