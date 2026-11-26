<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Key\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexVisibility;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(IndexVisibility::class)]
#[Medium]
final class IndexVisibilityTest extends TestCase
{
    public function testRenderWritesTheOption(): void
    {
        $create = (new Inspection())->inspect('CREATE TABLE t (a INT, KEY (a) VISIBLE)');
        $statement = $create->statement;
        self::assertInstanceOf(CreateTable::class, $statement);
        $statement = $create->statement;
        self::assertInstanceOf(CreateTable::class, $statement);
        $index = $statement->elements[1];
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition::class, $index);
        $option = $index->options[0];

        self::assertInstanceOf(IndexVisibility::class, $option);
        self::assertTrue($option->visible);
        self::assertSame('CREATE TABLE t (a INT, INDEX (a) VISIBLE)', $create->toString());
    }
}
