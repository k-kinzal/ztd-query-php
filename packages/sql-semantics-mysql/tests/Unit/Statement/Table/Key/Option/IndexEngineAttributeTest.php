<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Key\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexEngineAttribute;

#[CoversClass(IndexEngineAttribute::class)]
#[Medium]
final class IndexEngineAttributeTest extends TestCase
{
    public function testRenderWritesTheOption(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a INT, KEY (a) ENGINE_ATTRIBUTE = \'{}\')');
        $statement = $create->statement;
        self::assertInstanceOf(CreateTable::class, $statement);
        $statement = $create->statement;
        self::assertInstanceOf(CreateTable::class, $statement);
        $index = $statement->elements[1];
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition::class, $index);
        $option = $index->options[0];

        self::assertInstanceOf(IndexEngineAttribute::class, $option);
        self::assertFalse($option->secondary);
        self::assertSame('CREATE TABLE t (a INT, INDEX (a) ENGINE_ATTRIBUTE \'{}\')', $create->toString());
    }
}
