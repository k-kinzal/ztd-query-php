<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Option\StorageOption;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(StorageOption::class)]
#[Medium]
final class StorageOptionTest extends TestCase
{
    public function testRenderWritesTheOption(): void
    {
        $create = (new Inspection())->inspect('CREATE TABLE t (a INT) STORAGE DISK');
        $statement = $create->statement;
        self::assertInstanceOf(CreateTable::class, $statement);
        $statement = $create->statement;
        self::assertInstanceOf(CreateTable::class, $statement);
        $option = $statement->options[0];

        self::assertInstanceOf(StorageOption::class, $option);
        self::assertSame(\SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\StorageMedium::Disk, $option->medium);
        self::assertSame('CREATE TABLE t (a INT) STORAGE DISK', $create->toString());
    }
}
