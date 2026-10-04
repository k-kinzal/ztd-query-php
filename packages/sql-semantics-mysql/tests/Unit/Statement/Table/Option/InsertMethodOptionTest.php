<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Option\InsertMethodOption;

#[CoversClass(InsertMethodOption::class)]
#[Medium]
final class InsertMethodOptionTest extends TestCase
{
    public function testRenderWritesTheOption(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a INT) INSERT_METHOD = FIRST');
        $statement = $create->statement;
        self::assertInstanceOf(CreateTable::class, $statement);
        $statement = $create->statement;
        self::assertInstanceOf(CreateTable::class, $statement);
        $option = $statement->options[0];

        self::assertInstanceOf(InsertMethodOption::class, $option);
        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Table\Option\Kind\InsertMethod::First, $option->method);
        self::assertSame('CREATE TABLE t (a INT) INSERT_METHOD FIRST', $create->toString());
    }
}
