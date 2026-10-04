<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Element;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\Element\PartitionOf::class)]
#[Medium]
final class PartitionOfTest extends TestCase
{
    public function testElementsAnswersTheColumnOptions(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE p1 PARTITION OF p (a NOT NULL, CHECK (a > 0)) DEFAULT', []);
        $n1 = $statement->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\CreateTable::class, $n1);
        $n2 = $n1->definition;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Element\PartitionOf::class, $n2);
        self::assertSame(2, count($n2->elements()));
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('CREATE TABLE p1 PARTITION OF s.p (a NOT NULL) FOR VALUES IN (1, 2)', []);
        self::assertSame('CREATE TABLE p1 PARTITION OF s.p (a NOT NULL) FOR VALUES IN (1, 2)', $statement->toString());
    }
}
