<?php

declare(strict_types=1);

namespace Tests\Unit\Semantic\Statement;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use Tests\Scenario\SemanticCases;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Semantic\Statement\InsertSelect::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class InsertSelectTest extends TestCase
{
    public function testWithSourceAndToStringKeepDistinctStatementTypes(): void
    {
        $values = (new Semantics(Dialect::Sqlite))->analyze('INSERT INTO bar (foo) VALUES (1)');
        self::assertInstanceOf(\SqlSemantics\Semantic\Statement\InsertRows::class, $values);
        $select = new \SqlSemantics\Semantic\Statement\InsertSelect($values->target, SemanticCases::select(Dialect::Sqlite));
        $updated = $select->withSource(SemanticCases::select(Dialect::Sqlite, 'SELECT 7'));
        self::assertSame('INSERT INTO bar (foo) SELECT 7', $updated->toString());
        self::assertSame('INSERT INTO bar (foo) VALUES (1)', $values->toString());
    }
    public function testToStringPreservesTheIndependentSource(): void
    {
        self::assertSame('INSERT INTO bar (foo) SELECT foo FROM other', (new Semantics(Dialect::Sqlite))->analyze('INSERT INTO bar (foo) SELECT foo FROM other')->toString());
    }
}
