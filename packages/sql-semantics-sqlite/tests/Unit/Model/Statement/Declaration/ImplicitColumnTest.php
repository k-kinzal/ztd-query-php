<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Declaration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Model\Statement\Declaration\Column;
use SqlSemantics\Platform\Sqlite\Model\Statement\Declaration\ImplicitColumn;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(ImplicitColumn::class)]
#[Medium]
final class ImplicitColumnTest extends TestCase
{
    public function testEveryNameFindsTheColumnAndItIsNotPartOfTheStar(): void
    {
        $semantics = new Inspection();
        $table = $semantics->inspect('CREATE TABLE t (a TEXT)')->declarations()[0];

        $query = $semantics->inspect('SELECT *, oid FROM t', [$table]);
        $fields = $query->fields();

        self::assertCount(1, $table->implicit);
        self::assertSame(['rowid', 'oid', '_rowid_'], array_map(static fn (Name $name): string => $name->value, $table->implicit[0]->names));
        self::assertNotNull($fields);
        self::assertCount(2, $fields);
        self::assertSame('a', $fields->at(0)->name?->value);
        self::assertSame($table->implicit[0]->column, $fields->at(1)->column());
        self::assertSame('rowid', $fields->at(1)->name?->value);
    }

    public function testAnImplicitColumnNamesAtLeastOneName(): void
    {
        $this->expectExceptionMessage('An implicit column has at least one name.');

        new ImplicitColumn([], new Column(new Name('rowid'), Storage::Integer));
    }
}
