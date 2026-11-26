<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Option\TablespaceOption;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(TablespaceOption::class)]
#[Medium]
final class TablespaceOptionTest extends TestCase
{
    public function testRenderWritesTheOption(): void
    {
        $create = (new Inspection())->inspect('CREATE TABLE t (a INT) TABLESPACE = ts');
        $statement = $create->statement;
        self::assertInstanceOf(CreateTable::class, $statement);
        $statement = $create->statement;
        self::assertInstanceOf(CreateTable::class, $statement);
        $option = $statement->options[0];

        self::assertInstanceOf(TablespaceOption::class, $option);
        self::assertSame('ts', $option->tablespace->value);
        self::assertSame('CREATE TABLE t (a INT) TABLESPACE ts', $create->toString());
    }
}
