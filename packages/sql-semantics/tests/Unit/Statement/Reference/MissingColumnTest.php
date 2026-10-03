<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Reference;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Expression\ColumnReference;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\MissingColumn;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Schema\Catalog;
use SqlSemantics\Statement\Schema\SearchPath;

#[CoversClass(MissingColumn::class)]
#[Small]
final class MissingColumnTest extends TestCase
{
    public function testAnEmptyScopeCannotOwnAColumnEvenWithoutACatalog(): void
    {
        $catalog = new Catalog(new SearchPath(new Name('main')), complete: false);
        $column = new ColumnReference(new Scope($catalog), new Name('id'));
        self::assertSame(MissingColumn::Value, $column->resolution);
    }
}
