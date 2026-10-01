<?php

declare(strict_types=1);

namespace Tests\Unit\Semantic\Statement;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use Tests\Scenario\SemanticCases;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Semantic\Statement\InsertTarget::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class InsertTargetTest extends TestCase
{
    public function testToStringKeepsOrderedReferencesToTheTargetDeclaration(): void
    {
        $table = SemanticCases::table(Dialect::Sqlite);
        $statement = (new Semantics(Dialect::Sqlite))->analyze('INSERT INTO bar (label, foo) VALUES (\'a\', 1)', [$table]);
        self::assertInstanceOf(\SqlSemantics\Semantic\Statement\InsertRows::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Semantic\Reference\ResolvedColumn::class, $statement->target->columns[0]->binding);
        self::assertSame($table->columns[1], $statement->target->columns[0]->binding->column);
        self::assertSame('bar (label, foo)', $statement->target->toString());
    }
}
