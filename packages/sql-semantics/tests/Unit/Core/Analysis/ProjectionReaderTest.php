<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Analysis;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Dialect;
use Tests\Scenario\SemanticCases;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Analysis\ProjectionReader::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class ProjectionReaderTest extends TestCase
{
    public function testReadPreservesDuplicateOutputPositions(): void
    {
        $statement = SemanticCases::select(Dialect::Sqlite, 'SELECT foo, foo FROM bar');
        self::assertCount(2, $statement->fields()->items);
    }

    public function testItemDoesNotConfuseAnAliasWithItsColumn(): void
    {
        $statement = SemanticCases::select(Dialect::Sqlite, 'SELECT foo AS label FROM bar');
        self::assertSame('integer', $statement->field('label')->type->name);
    }
}
