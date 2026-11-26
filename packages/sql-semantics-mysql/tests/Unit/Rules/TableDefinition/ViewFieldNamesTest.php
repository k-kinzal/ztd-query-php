<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableDefinition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\ViewFieldNames;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ViewFieldNames::class)]
#[Medium]
final class ViewFieldNamesTest extends TestCase
{
    public function testAssignedAliasRetainsWholeCharacters(): void
    {
        $names = new ViewFieldNames();

        self::assertSame('K', $names->assignedAlias(" \tK"));
        self::assertNull($names->assignedAlias(str_repeat('漢', 86)));
    }

    public function testItemsRetainsWrittenAliasesAtTheirOwnOccurrences(): void
    {
        $command = (new Inspection())->inspect('SELECT 7 AS first_name, 11 AS second_name')->statement;
        self::assertInstanceOf(Select::class, $command);

        $items = (new ViewFieldNames())->items($command);
        self::assertSame(['first_name', 'second_name'], array_values(array_map(static fn ($item): ?string => $item->alias?->value, $items)));
    }
}
