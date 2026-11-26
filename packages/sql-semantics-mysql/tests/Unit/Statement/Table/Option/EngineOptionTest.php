<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Option\EngineOption;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(EngineOption::class)]
#[Medium]
final class EngineOptionTest extends TestCase
{
    public function testRenderWritesTheOption(): void
    {
        $create = (new Inspection())->inspect('CREATE TABLE t (a INT) ENGINE = \'InnoDB\'');
        $statement = $create->statement;
        self::assertInstanceOf(CreateTable::class, $statement);
        $statement = $create->statement;
        self::assertInstanceOf(CreateTable::class, $statement);
        $option = $statement->options[0];

        self::assertInstanceOf(EngineOption::class, $option);
        self::assertSame('InnoDB', $option->engine->value);
        self::assertSame('CREATE TABLE t (a INT) ENGINE InnoDB', $create->toString());
    }
}
