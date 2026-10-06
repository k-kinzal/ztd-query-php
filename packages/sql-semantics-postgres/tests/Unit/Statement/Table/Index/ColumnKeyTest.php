<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Index;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\Index\ColumnKey::class)]
#[Medium]
final class ColumnKeyTest extends TestCase
{
    public function testDeriveScalarResolvesTheColumn(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $context = [];
        array_push($context, ...$semantics->analyze('CREATE TABLE t (a int NOT NULL, b int, c text)')->declarations());
        $statement = $semantics->analyze('CREATE INDEX ON t (b)', $context);
        $n1 = $statement->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Index\CreateIndex::class, $n1);
        $n2 = $n1->elements[0]->key;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Index\ColumnKey::class, $n2);
        $n3 = $statement->facts->scalar($n2)->resolution;
        self::assertInstanceOf(\SqlSemantics\Statement\Reference\Column\ResolvedColumn::class, $n3);
        self::assertSame(true, $n3->slot->column === $context[0]->columns[1]);
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE INDEX ON t ("A b")', []);
        self::assertSame('CREATE INDEX ON t ("A b")', $statement->toString());
    }
}
