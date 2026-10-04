<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Key\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Key\Option\KeyBlockSize;

#[CoversClass(KeyBlockSize::class)]
#[Medium]
final class KeyBlockSizeTest extends TestCase
{
    public function testRenderWritesTheOption(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a INT, KEY (a) KEY_BLOCK_SIZE = 8)');
        $statement = $create->statement;
        self::assertInstanceOf(CreateTable::class, $statement);
        $statement = $create->statement;
        self::assertInstanceOf(CreateTable::class, $statement);
        $index = $statement->elements[1];
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition::class, $index);
        $option = $index->options[0];

        self::assertInstanceOf(KeyBlockSize::class, $option);
        self::assertSame('8', $option->size->text);
        self::assertSame('CREATE TABLE t (a INT, INDEX (a) KEY_BLOCK_SIZE 8)', $create->toString());
    }
}
