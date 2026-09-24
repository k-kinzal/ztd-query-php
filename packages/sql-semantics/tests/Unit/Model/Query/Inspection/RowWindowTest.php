<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Inspection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Query\Inspection\RowWindow;
use SqlSemantics\Model\Statement\Inspection\Session\ShowDiagnosticsStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RowWindow::class)]
#[Medium]
final class RowWindowTest extends TestCase
{
    public function testCommaWindowListsTheOffsetBeforeTheCount(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW WARNINGS LIMIT 2, 5');
        self::assertInstanceOf(ShowDiagnosticsStatement::class, $statement);
        self::assertNotNull($statement->limit);
        self::assertSame(['5', '2'], [$statement->limit->count->spelling(), $statement->limit->offset?->spelling()]);
        self::assertSame('SHOW WARNINGS LIMIT 5 OFFSET 2', $statement->toString());
    }

    public function testOffsetIsOptional(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW ERRORS LIMIT ?');
        self::assertInstanceOf(ShowDiagnosticsStatement::class, $statement);
        self::assertSame('?', $statement->limit?->count->spelling());
        self::assertNull($statement->limit->offset);
    }

    public function testRejectsOperandsFromAnotherDialect(): void
    {
        $select = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1');
        self::assertInstanceOf(BoundSelect::class, $select);
        $this->expectException(InvalidStructure::class);
        new RowWindow($select->outputs[0]->expression);
    }
}
