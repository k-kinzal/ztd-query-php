<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Analysis;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Dialect;
use Tests\Scenario\SemanticCases;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Analysis\ModifiersReader::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class ModifiersReaderTest extends TestCase
{
    public function testOrderingPreservesNullPlacement(): void
    {
        self::assertTrue(SemanticCases::select(Dialect::Sqlite, 'SELECT foo FROM bar ORDER BY foo NULLS FIRST')->orderBy[0]->nullsFirst);
    }

    public function testOutputRejectsAnAmbiguousOutputName(): void
    {
        $this->expectException(\SqlSemantics\Core\SemanticException::class);
        SemanticCases::select(Dialect::Sqlite, 'SELECT foo AS x, label AS x FROM bar ORDER BY x');
    }

    public function testPaginationResolvesCommaOffsetInItsCorrectOrder(): void
    {
        $statement = SemanticCases::select(Dialect::Sqlite, 'SELECT foo FROM bar LIMIT 3, 5');
        self::assertNotNull($statement->limit);
        self::assertNotNull($statement->offset);
        self::assertSame('5', $statement->limit->toString());
        self::assertSame('3', $statement->offset->toString());
    }

    public function testCountRetainsAParameterWithoutInventingItsValue(): void
    {
        $statement = SemanticCases::select(Dialect::Sqlite, 'SELECT foo FROM bar LIMIT ?');
        self::assertInstanceOf(\SqlSemantics\Semantic\Expression\Parameter::class, $statement->limit);
    }
}
