<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Option\CollationOption;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(CollationOption::class)]
#[Medium]
final class CollationOptionTest extends TestCase
{
    public function testRenderWritesTheOption(): void
    {
        $create = (new Inspection())->inspect('CREATE TABLE t (a INT) DEFAULT COLLATE utf8mb4_bin');
        $statement = $create->statement;
        self::assertInstanceOf(CreateTable::class, $statement);
        $statement = $create->statement;
        self::assertInstanceOf(CreateTable::class, $statement);
        $option = $statement->options[0];

        self::assertInstanceOf(CollationOption::class, $option);
        self::assertSame('utf8mb4_bin', $option->collation->name?->value);
        self::assertSame('CREATE TABLE t (a INT) COLLATE utf8mb4_bin', $create->toString());
    }
}
