<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Loading\Copy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Statement\Loading\Copy\ColumnChoice;
use SqlSemantics\Model\Statement\Loading\Copy\EveryColumn;
use SqlSemantics\Model\Statement\Loading\Copy\ListedColumns;

#[CoversClass(ColumnChoice::class)]
final class ColumnChoiceTest extends TestCase
{
    public function testEveryAndListedColumnsAreChoices(): void
    {
        self::assertSame([ColumnChoice::class], array_values(class_implements(new EveryColumn())));
        self::assertSame([ColumnChoice::class], array_values(class_implements(new ListedColumns(['a']))));
    }
}
