<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Option\AutoextendSizeOption;

#[CoversClass(AutoextendSizeOption::class)]
#[Medium]
final class AutoextendSizeOptionTest extends TestCase
{
    public function testRenderWritesTheOption(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a INT) AUTOEXTEND_SIZE = 4M');
        $statement = $create->statement;
        self::assertInstanceOf(CreateTable::class, $statement);
        $statement = $create->statement;
        self::assertInstanceOf(CreateTable::class, $statement);
        $option = $statement->options[0];

        self::assertInstanceOf(AutoextendSizeOption::class, $option);
        self::assertSame('4M', $option->size->word?->value);
        self::assertSame('CREATE TABLE t (a INT) AUTOEXTEND_SIZE `4M`', $create->toString());
    }
}
