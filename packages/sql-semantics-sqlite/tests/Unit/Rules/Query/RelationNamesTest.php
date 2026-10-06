<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Rules\Query\RelationNames;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Query\ValuesClause;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Missing\MissingInput;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\OutputSlot;

#[CoversClass(RelationNames::class)]
#[Medium]
final class RelationNamesTest extends TestCase
{
    public function testShapeNamesTheColumnsAsSqliteDoesBeforeResolution(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $create = $semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)');
        $query = $semantics->analyze('SELECT a COLLATE nocase, true, a, a, a, (a), a, t.b, 1 + 1, "zz", oid, A FROM t', [$create]);
        self::assertInstanceOf(Select::class, $query->statement);
        $shape = (new RelationNames())->shape($query->facts->output ?? self::fail('The query has an output.'), $query->statement, new Derivation($semantics->context()));

        self::assertSame(['a', 'column2', 'a:1', 'a:2', 'a:3', 'a:4', null, 'b', '1 + 1', 'zz', 'oid', null], array_map(static fn (OutputSlot $slot): ?string => $slot->name?->value, $shape->slots));
        self::assertSame($query->field(0)->slot, $shape->slots[0]->origin);
        self::assertSame($query->field(0)->type, $shape->slots[0]->type);
        self::assertTrue($shape->complete());
    }

    public function testShapeLetsAnAliasWinAndCountsItAmongTheDuplicates(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $query = $semantics->analyze('SELECT a AS a, a, a AS "a:1", b AS A FROM t');
        self::assertInstanceOf(Select::class, $query->statement);
        $shape = (new RelationNames())->shape($query->facts->output ?? self::fail('The query has an output.'), $query->statement, new Derivation($semantics->context()));

        self::assertSame(['a', 'a:1', 'a:2', 'A:3'], array_map(static fn (OutputSlot $slot): ?string => $slot->name?->value, $shape->slots));
    }

    public function testShapeKeepsTheMissingInputsAndLeavesTheNamesAfterAnOpenStarUnnamed(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $query = $semantics->analyze('SELECT a, *, b FROM t');
        self::assertInstanceOf(Select::class, $query->statement);
        $shape = (new RelationNames())->shape($query->facts->output ?? self::fail('The query has an output.'), $query->statement, new Derivation($semantics->context()));

        self::assertSame(['a', null], array_map(static fn (OutputSlot $slot): ?string => $slot->name?->value, $shape->slots));
        self::assertSame([[], ['the declaration of relation t']], array_map(static fn (OutputSlot $slot): array => array_map(static fn (MissingInput $input): string => $input->describe(), $slot->unnamed), $shape->slots));
        self::assertFalse($shape->complete());
        self::assertSame('the declaration of relation t', $shape->missing[0]->describe());
    }

    public function testShapeKeepsAnUndecidedNameAndTheNamesAfterItDependent(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $query = $semantics->analyze('SELECT *, 2 AS two FROM (SELECT *, 1 AS one FROM u)');
        self::assertInstanceOf(Select::class, $query->statement);
        $shape = (new RelationNames())->shape($query->facts->output ?? self::fail('The query has an output.'), $query->statement, new Derivation($semantics->context()));

        self::assertSame([null, null], array_map(static fn (OutputSlot $slot): ?string => $slot->name?->value, $shape->slots));
        self::assertSame('the declaration of relation u', $shape->slots[0]->unnamed[0]->describe());
        self::assertNotSame([], $shape->slots[1]->unnamed);
    }

    public function testShapeNamesTheColumnsAfterTheLeftmostArm(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('SELECT * FROM (SELECT 1+1, 2 AS b UNION SELECT 3, 4), (VALUES ("zz", 1, true, "q" COLLATE nocase))', []);

        self::assertSame(['1+1', 'b', 'zz', 'column2', 'column3', 'q'], array_map(static fn (Field $field): ?string => $field->name?->value, $query->fields()->items ?? []));
    }

    public function testNamedPrefersTheAliasThenTheWrittenWordThenTheText(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $create = $semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)');
        $query = $semantics->analyze('SELECT oid, a AS x, "zz", true, TRUE AS z, 1, * FROM t', [$create]);
        $names = new RelationNames();
        $select = $query->statement;

        self::assertInstanceOf(Select::class, $select);
        self::assertSame('oid', $names->named($query->field(0), $select, 0)?->value);
        self::assertSame('x', $names->named($query->field(1), $select, 1)?->value);
        self::assertSame('zz', $names->named($query->field(2), $select, 2)?->value);
        self::assertSame('true', $names->named($query->field(3), $select, 3)?->value);
        self::assertSame('z', $names->named($query->field(4), $select, 4)?->value);
        self::assertSame('1', $names->named($query->field(5), $select, 5)?->value);
        self::assertSame('a', $names->named($query->field(6), $select, 6)?->value);
    }

    public function testNamedReadsTheWordOfAValuesColumnOrItsPosition(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('VALUES (x, 1)');
        $values = $query->statement;

        self::assertInstanceOf(ValuesClause::class, $values);
        self::assertSame('x', (new RelationNames())->named($query->field(0), $values, 0)?->value);
        self::assertSame('column2', (new RelationNames())->named($query->field(1), $values, 1)?->value);
    }

    public function testListedRenamesTruthWordsAndRepeatedNames(): void
    {
        self::assertSame(['column1', 'a', 'A:1'], array_map(static fn (?Name $name): ?string => $name?->value, (new RelationNames())->listed([new Name('true'), new Name('a'), new Name('A')])));
        self::assertSame([], (new RelationNames())->listed([]));
    }

    public function testUniqueAppendsACounterUntilTheFourthAttempt(): void
    {
        $names = new RelationNames();
        $name = new Name('a');

        self::assertSame($name, $names->unique($name, []));
        self::assertSame($name, $names->unique($name, ['b' => true]));
        self::assertSame('a:1', $names->unique($name, ['a' => true])?->value);
        self::assertSame('a:3', $names->unique($name, ['a' => true, 'a:1' => true, 'a:2' => true])?->value);
        self::assertSame('a:4', $names->unique($name, ['a' => true, 'a:1' => true, 'a:2' => true, 'a:3' => true])?->value);
        self::assertNull($names->unique($name, ['a' => true, 'a:1' => true, 'a:2' => true, 'a:3' => true, 'a:4' => true]));
        self::assertNull($names->unique(null, []));
    }

    public function testUniqueDropsAnExistingCounterAndComparesWithoutCase(): void
    {
        $names = new RelationNames();

        self::assertSame('a:1', $names->unique(new Name('a:7'), ['a:7' => true])?->value);
        self::assertSame('A:2', $names->unique(new Name('A'), ['a' => true, 'a:1' => true])?->value);
        self::assertSame(':1', $names->unique(new Name(':9'), [':9' => true])?->value);
    }
}
