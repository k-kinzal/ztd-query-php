<?php

declare(strict_types=1);

namespace Tests\Unit\Rules;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Rules\ClosedList;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Star;
use SqlSemantics\Platform\Sqlite\Statement\Query\TableStar;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(ClosedList::class)]
#[Small]
final class ClosedListTest extends TestCase
{
    public function testOfAnswersTheItemsInOrderWhenEachIsOfAnAdmittedClass(): void
    {
        $star = new Star();
        $table = new TableStar(new Name('t'));
        $list = (new ClosedList())->of([$star, $table], [Star::class, TableStar::class], 'Result columns.');

        self::assertSame([$star, $table], $list);
    }

    public function testOfAcceptsAnEmptyListWithoutAMinimum(): void
    {
        self::assertSame([], (new ClosedList())->of([], [Star::class], 'Result columns.'));
    }

    public function testOfAcceptsAListThatReachesTheMinimum(): void
    {
        $star = new Star();

        self::assertSame([$star], (new ClosedList())->of([$star], [Star::class, ResultColumn::class], 'Result columns.', 1));
    }

    public function testOfAdmitsAnItemByAnyOfTheClasses(): void
    {
        $name = new Name('a');
        $qualified = new QualifiedName(new Name('t'));

        self::assertSame([$name, $qualified], (new ClosedList())->of([$name, $qualified], [QualifiedName::class, Name::class], 'Names.'));
    }
}
