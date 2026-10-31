<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query\From;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Query\From\RelationNames;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable;
use SqlSemantics\Platform\MySql\Statement\Relation\Dual;
use SqlSemantics\Platform\MySql\Statement\Relation\JoinedTable;
use SqlSemantics\Platform\MySql\Statement\Relation\Join\JoinOperator;
use SqlSemantics\Platform\MySql\Statement\Relation\TableList;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\NonUniqueTable;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

#[CoversClass(RelationNames::class)]
#[Small]
final class RelationNamesTest extends TestCase
{
    public function testRepeatedFindsTheLaterOfTwoRelationsWithOneName(): void
    {
        $rules = new RelationNames();
        $first = new TableReference(new QualifiedName(new Name('t')));
        $second = new TableReference(new QualifiedName(new Name('t')));
        $aliased = new TableReference(new QualifiedName(new Name('u')), new Name('t'));
        $derived = new DerivedTable(new Select([], [new SelectExpression(new NumberLiteral('1'))]), new Name('t'));
        $other = new TableReference(new QualifiedName(new Name('t'), new Name('d')));

        $problem = $rules->repeated([new TableList([$first, $second])]);
        self::assertInstanceOf(NonUniqueTable::class, $problem);
        self::assertSame('t', $problem->alias->value);
        self::assertSame($second, $problem->relation);
        self::assertSame($aliased, $rules->repeated([new JoinedTable($first, JoinOperator::Inner, $aliased)])?->relation);
        self::assertNull($rules->repeated([new TableList([$first, $derived])]));
        self::assertNull($rules->repeated([new TableList([$first, $other])]));
        self::assertNull($rules->repeated([new Dual()]));
        self::assertNull($rules->repeated([null]));
    }

    public function testRepeatedTablesComparesTheAliasOrNameWithinTheDatabase(): void
    {
        $rules = new RelationNames();
        $table = new QualifiedName(new Name('t'));
        $qualified = new QualifiedName(new Name('t'), new Name('d'));

        self::assertSame('t', $rules->repeatedTables([[$table, null, null], [$qualified, null, null], [$table, null, null]])?->alias->value);
        self::assertSame('a', $rules->repeatedTables([[$table, new Name('a'), null], [$table, new Name('a'), null]])?->alias->value);
        self::assertNull($rules->repeatedTables([[$table, new Name('a'), null], [$qualified, new Name('a'), null]]));
        self::assertNull($rules->repeatedTables([[$table, null, null], [$qualified, null, null]]));
        self::assertNull($rules->repeatedTables([[$table, new Name('a'), null], [$table, new Name('b'), null]]));
    }

    public function testVisibleListsTheRelationsOfNestedTreesInWrittenOrder(): void
    {
        $left = new TableReference(new QualifiedName(new Name('a')));
        $right = new TableReference(new QualifiedName(new Name('b'), new Name('d')), new Name('r'));
        $derived = new DerivedTable(new Select([], [new SelectExpression(new NumberLiteral('1'))]), new Name('v'));

        $visible = (new RelationNames())->visible([new TableList([new JoinedTable($left, JoinOperator::Inner, $right), $derived])]);

        self::assertSame(['a', 'r', 'v'], array_map(static fn (array $entry): string => $entry[1]->value, $visible));
        self::assertSame([$left, $right, $derived], array_map(static fn (array $entry) => $entry[2], $visible));
    }
}
