<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Analysis;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Analysis\InsertReader::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class InsertReaderTest extends TestCase
{
    public function testTargetRetainsTheOrderOfColumnAssignments(): void
    {
        $statement = (new Semantics(Dialect::Sqlite))->analyze('INSERT INTO bar (label, foo) VALUES (\'a\', 1)');
        self::assertInstanceOf(\SqlSemantics\Semantic\Statement\InsertRows::class, $statement);
        self::assertSame('label', $statement->target->columns[0]->name->value);
    }

    public function testValuesAreNotCollapsedIntoASourceQuery(): void
    {
        self::assertInstanceOf(\SqlSemantics\Semantic\Statement\InsertRows::class, (new Semantics(Dialect::Sqlite))->analyze('INSERT INTO bar (foo) VALUES (1), (2)'));
    }

    public function testSelectKeepsItsOwnSourceScope(): void
    {
        $statement = (new Semantics(Dialect::Sqlite))->analyze('INSERT INTO bar (foo) SELECT foo FROM other');
        self::assertInstanceOf(\SqlSemantics\Semantic\Statement\InsertSelect::class, $statement);
        self::assertNotSame($statement->target->scope, $statement->source->scope);
        self::assertSame('other', $statement->source->tables[0]->name->name->value);
    }
}
