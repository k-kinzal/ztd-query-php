<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Policy;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Dialect;
use Tests\Scenario\SemanticCases;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Policy\RelationRules::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class RelationRulesTest extends TestCase
{
    public function testRelationKeepsAnOccurrenceSeparateFromItsDeclaration(): void
    {
        $statement = SemanticCases::select(Dialect::Sqlite, 'SELECT b.foo FROM bar b');
        self::assertNotNull($statement->tables[0]->alias);
        self::assertNotNull($statement->tables[0]->declaration);
        self::assertSame('b', $statement->tables[0]->alias->value);
        self::assertSame('bar', $statement->tables[0]->declaration->name->name->value);
    }
}
