<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema\Limit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Limit\DefinitionPosition;

#[CoversClass(DefinitionPosition::class)]
#[Small]
final class DefinitionPositionTest extends TestCase
{
    public function testCasesNameEachPositionWithThePhraseOfTheManual(): void
    {
        self::assertSame(['CheckConstraint', 'PartialIndexWhere', 'IndexExpression', 'GeneratedColumn'], array_column(DefinitionPosition::cases(), 'name'));
        self::assertSame('partial index WHERE clauses', DefinitionPosition::PartialIndexWhere->value);
    }
}
