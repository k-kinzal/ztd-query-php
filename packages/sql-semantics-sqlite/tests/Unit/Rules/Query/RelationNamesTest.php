<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Rules\Query\RelationNames;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Collate;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ColumnUse;
use SqlSemantics\Platform\Sqlite\Statement\Expression\DoubleQuotedWord;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Grouped;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\TruthWord;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Shape\OutputSlot;

#[CoversClass(RelationNames::class)]
#[Medium]
final class RelationNamesTest extends TestCase
{
    public function testShapeNamesTheColumnsAsSqliteDoesBeforeResolution(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $create = $semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)');
        $query = $semantics->analyze('SELECT a COLLATE nocase, true, a, a, a, (a), a, t.b, 1 + 1, "zz", oid FROM t', [$create]);
        $shape = (new RelationNames())->shape($query->facts->output ?? self::fail('The query has an output.'));

        self::assertSame(['a', 'column2', 'a:1', 'a:2', 'a:3', 'a:4', null, 'b', null, 'zz', 'oid'], array_map(static fn (OutputSlot $slot): ?string => $slot->name?->value, $shape->slots));
        self::assertSame($query->field(0)->slot, $shape->slots[0]->origin);
        self::assertSame($query->field(0)->type, $shape->slots[0]->type);
        self::assertTrue($shape->complete());
    }

    public function testShapeLetsAnAliasWinAndCountsItAmongTheDuplicates(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('SELECT a AS a, a, a AS "a:1", b AS A FROM t');
        $shape = (new RelationNames())->shape($query->facts->output ?? self::fail('The query has an output.'));

        self::assertSame(['a', 'a:1', 'a:2', 'A:3'], array_map(static fn (OutputSlot $slot): ?string => $slot->name?->value, $shape->slots));
    }

    public function testShapeKeepsTheMissingInputsAndSkipsOpenStars(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('SELECT a, * FROM t');
        $shape = (new RelationNames())->shape($query->facts->output ?? self::fail('The query has an output.'));

        self::assertCount(1, $shape->slots);
        self::assertFalse($shape->complete());
        self::assertSame('the declaration of relation t', $shape->missing[0]->describe());
    }

    public function testNamedPrefersTheWrittenWordAndRenamesATruthWord(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $create = $semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)');
        $query = $semantics->analyze('SELECT oid, a AS x, "zz", true, TRUE AS z, 1 FROM t', [$create]);
        $names = new RelationNames();

        self::assertSame('oid', $names->named($query->field(0), 0)?->value);
        self::assertSame('x', $names->named($query->field(1), 1)?->value);
        self::assertSame('zz', $names->named($query->field(2), 2)?->value);
        self::assertSame('column4', $names->named($query->field(3), 3)?->value);
        self::assertSame('z', $names->named($query->field(4), 4)?->value);
        self::assertNull($names->named($query->field(5), 5));
    }

    public function testWrittenAnswersTheOneWordAnExpressionConsistsOf(): void
    {
        $names = new RelationNames();

        self::assertSame('a', $names->written(new ColumnUse(new Name('a'), new QualifiedName(new Name('t'))))?->value);
        self::assertSame('a', $names->written(new Grouped(new Collate(new ColumnUse(new Name('a')), new Name('nocase'))))?->value);
        self::assertSame('zz', $names->written(new DoubleQuotedWord(new Name('zz')))?->value);
        self::assertSame('false', $names->written(new TruthWord(false))?->value);
        self::assertNull($names->written(new IntegerLiteral('1')));
        self::assertNull($names->written(null));
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
        self::assertSame(':9:1', $names->unique(new Name(':9'), [':9' => true])?->value);
    }
}
