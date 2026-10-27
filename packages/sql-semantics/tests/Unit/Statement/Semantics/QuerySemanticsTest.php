<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Semantics;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Statement\Semantics\QuerySemantics::class)]
#[Small]
final class QuerySemanticsTest extends TestCase
{
    public function testInitializeQueryInformationRetainsOneBinding(): void
    {
        $node = new class () {
            use \SqlSemantics\Statement\Semantics\QuerySemantics;
        };
        $information = new \SqlSemantics\Statement\Fact\QueryFact([], \SqlSemantics\Statement\Identifier\Comparison::Sensitive);
        $node->initializeQueryInformation($information);
        $node->initializeQueryInformation($information);
        self::assertSame($information, $node->queryInformation());
        $this->expectExceptionMessage('A query occurrence is resolved once.');
        $node->initializeQueryInformation(new \SqlSemantics\Statement\Fact\QueryFact([], \SqlSemantics\Statement\Identifier\Comparison::Sensitive));
    }

    public function testQueryInformationRequiresCompletedConstruction(): void
    {
        $node = new class () {
            use \SqlSemantics\Statement\Semantics\QuerySemantics;
        };
        $this->expectExceptionMessage('A query occurrence must be resolved before use.');
        $node->queryInformation();
    }

    public function testHasQueryInformationChangesWhenTheBindingIsSupplied(): void
    {
        $node = new class () {
            use \SqlSemantics\Statement\Semantics\QuerySemantics;
        };
        self::assertFalse($node->hasQueryInformation());
        $node->initializeQueryInformation(new \SqlSemantics\Statement\Fact\QueryFact([], \SqlSemantics\Statement\Identifier\Comparison::Sensitive));
        self::assertTrue($node->hasQueryInformation());
    }
}
