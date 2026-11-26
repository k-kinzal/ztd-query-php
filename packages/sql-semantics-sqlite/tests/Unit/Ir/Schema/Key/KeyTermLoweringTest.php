<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Key;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Collation;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Key\KeyTermLowering;
use SqlSemantics\Platform\Sqlite\Ir\Schema\TableColumn;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Collate;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ColumnUse;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\SortDirection;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\SortTerm;

#[CoversClass(KeyTermLowering::class)]
#[Small]
final class KeyTermLoweringTest extends TestCase
{
    public function testLowerResolvesCaseInsensitiveNameAndTheOuterCollation(): void
    {
        $column = new ColumnUse(new Name('CODE'));
        $expression = new Collate(new Collate($column, new Name('RTRIM')), new Name('nocase'));
        $term = new SortTerm($expression, SortDirection::Descending);
        $columns = [new TableColumn(new Identifier('message')), new TableColumn(new Identifier('code'))];

        $resolved = (new KeyTermLowering())->lower($term, $columns);

        self::assertSame(1, $resolved->slot);
        self::assertTrue($resolved->descending);
        self::assertSame(Collation::NoCase, $resolved->collation);
    }
}
