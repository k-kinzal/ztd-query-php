<?php

declare(strict_types=1);

namespace Tests\Unit\Ir;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\TableSource;

#[CoversClass(TableSource::class)]
#[Small]
final class TableSourceTest extends TestCase
{
    public function testCopiesColumnSlotsByValue(): void
    {
        $column = new Identifier('id');
        $source = new TableSource(new Identifier('main'), new Identifier('accounts'), [&$column]);
        $column = new Identifier('other');

        self::assertSame('id', $source->columns[0]->value);
    }

    public function testRejectsCaseInsensitiveDuplicates(): void
    {
        $this->expectExceptionMessage('repeat a column name');

        new TableSource(new Identifier('main'), new Identifier('accounts'), [new Identifier('id'), new Identifier('ID')]);
    }
}
