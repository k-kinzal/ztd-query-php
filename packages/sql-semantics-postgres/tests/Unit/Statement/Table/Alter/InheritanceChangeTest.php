<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Alter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\InheritanceChange::class)]
#[Medium]
final class InheritanceChangeTest extends TestCase
{
    public function testDeriveClauseResolvesTheParent(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('ALTER TABLE t INHERIT p', []);
        $n1 = $statement->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\AlterTable::class, $n1);
        $n2 = $n1->commands[0];
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\InheritanceChange::class, $n2);
        $n3 = $statement->facts->relation($n2->parent)->table;
        self::assertInstanceOf(\SqlSemantics\Statement\Reference\Table\MissingTable::class, $n3);
        self::assertSame('SqlSemantics\\Statement\\Reference\\Table\\MissingTable', $n3::class);
    }

    public function testRenderWritesTheClauseAsWritten(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $statement = $semantics->analyze('ALTER TABLE t NO INHERIT s.p', []);
        self::assertSame('ALTER TABLE t NO INHERIT s.p', $statement->toString());
    }
}
