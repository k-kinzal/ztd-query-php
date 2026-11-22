<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Option\NumberOption;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(NumberOption::class)]
#[Medium]
final class NumberOptionTest extends TestCase
{
    public function testRenderWritesTheOption(): void
    {
        $create = (new Inspection())->inspect('CREATE TABLE t (a INT) STATS_PERSISTENT = DEFAULT MAX_ROWS 10');
        $statement = $create->statement;
        self::assertInstanceOf(CreateTable::class, $statement);
        $statement = $create->statement;
        self::assertInstanceOf(CreateTable::class, $statement);
        $option = $statement->options[0];

        self::assertInstanceOf(NumberOption::class, $option);
        self::assertNull($option->value);
        self::assertSame('CREATE TABLE t (a INT) STATS_PERSISTENT DEFAULT MAX_ROWS 10', $create->toString());
    }
}
