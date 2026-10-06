<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Definition\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Rules\Definition\Query\ColumnTyping;
use SqlSemantics\Platform\Sqlite\Rules\Definition\Query\DerivedAffinity;
use SqlSemantics\Platform\Sqlite\Statement\Query\Compound;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Query\ValuesClause;
use SqlSemantics\Platform\Sqlite\Statement\Type\Affinity;
use SqlSemantics\Platform\Sqlite\Statement\Type\ColumnDomain;
use SqlSemantics\Platform\Sqlite\Statement\Type\NoAffinity;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;

#[CoversClass(ColumnTyping::class)]
#[Medium]
final class ColumnTypingTest extends TestCase
{
    public function testTextAffinityFollowsTheSubstringRulesAndIsNumericForAnEmptyText(): void
    {
        $rule = new ColumnTyping();

        self::assertSame(Affinity::Integer, $rule->textAffinity('point'));
        self::assertSame(Affinity::Text, $rule->textAffinity('VARCHAR(5)'));
        self::assertSame(Affinity::Blob, $rule->textAffinity('blob'));
        self::assertSame(Affinity::Real, $rule->textAffinity('DOUBLE'));
        self::assertSame(Affinity::Numeric, $rule->textAffinity(''));
        self::assertSame(Affinity::Numeric, $rule->textAffinity('ANY'));
    }

    public function testTextReadsTheDeclaredTypeAColumnReferenceCarries(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE s (n, e "", t VARCHAR(5), i INTEGER)');
        $query = $semantics->analyze('SELECT (t), t COLLATE nocase, n, e, rowid, (SELECT t FROM s UNION ALL SELECT i FROM s), 1, "t", zz FROM s', [$table]);
        $select = $query->statement;
        $rule = new ColumnTyping();

        self::assertInstanceOf(Select::class, $select);
        $texts = array_map(static fn (int $position): ?string => $rule->arm($select, $position, $query->facts), range(0, 8));
        self::assertSame(['VARCHAR(5)', null, null, '', 'INTEGER', 'INTEGER', null, 'VARCHAR(5)', null], $texts);
    }

    public function testResolvedReadsTheRightmostArmThroughADerivedTableAndACommonTable(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE s (t VARCHAR(5), t2 NVARCHAR(9))');
        $query = $semantics->analyze('WITH c AS (SELECT t AS x FROM s UNION ALL SELECT t2 FROM s) SELECT d.x, c.x, s.t FROM (SELECT t AS x FROM s UNION ALL SELECT t2 FROM s) AS d, c, s', [$table]);
        $rule = new ColumnTyping();
        $derived = $query->field(0)->resolution;
        $common = $query->field(1)->resolution;
        $declared = $query->field(2)->resolution;

        self::assertInstanceOf(ResolvedColumn::class, $derived);
        self::assertInstanceOf(ResolvedColumn::class, $common);
        self::assertInstanceOf(ResolvedColumn::class, $declared);
        self::assertSame('NVARCHAR(9)', $rule->resolved($derived, $query->facts));
        self::assertSame('NVARCHAR(9)', $rule->resolved($common, $query->facts));
        self::assertSame('VARCHAR(5)', $rule->resolved($declared, $query->facts));
    }

    public function testLeftmostReadsTheLeftmostArmOfADefiningQuery(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE s (t VARCHAR(5), t2 NVARCHAR(9))');
        $query = $semantics->analyze('SELECT t AS x FROM s UNION ALL SELECT t2 FROM s', [$table]);
        $compound = $query->statement;

        self::assertInstanceOf(Compound::class, $compound);
        self::assertSame('VARCHAR(5)', (new ColumnTyping())->leftmost($compound, 0, $query->facts));
        self::assertNull((new ColumnTyping())->leftmost($compound, 1, $query->facts));
    }

    public function testColumnReadsEachKindOfTypeDescriptor(): void
    {
        $rule = new ColumnTyping();

        self::assertSame('INT', $rule->column(new Column(new Name('a'), new ColumnDomain('INT'))));
        self::assertNull($rule->column(new Column(new Name('a'), new ColumnDomain(''))));
        self::assertSame('', $rule->column(new Column(new Name('a'), new ColumnDomain('', false, false))));
        self::assertNull($rule->column(new Column(new Name('a'), new NoAffinity())));
        self::assertSame('TEXT', $rule->column(new Column(new Name('a'), Storage::Text)));
    }

    public function testArmReadsAnExpandedStarAndARowValue(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE s (t VARCHAR(5))');
        $query = $semantics->analyze('SELECT * FROM s', [$table]);
        $rows = $semantics->analyze('VALUES (1)');
        $values = $rows->statement;
        $select = $query->statement;
        $rule = new ColumnTyping();

        self::assertInstanceOf(Select::class, $select);
        self::assertInstanceOf(ValuesClause::class, $values);
        self::assertSame('VARCHAR(5)', $rule->arm($select, 0, $query->facts));
        self::assertNull($rule->arm($select, 1, $query->facts));
        self::assertNull($rule->arm($values->rows[0], 0, $rows->facts));
        self::assertNull($rule->arm($values->rows[0], 1, $rows->facts));
    }

    public function testFieldReadsTheExpressionOrTheColumnAnExpandedStarExposes(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE s (t VARCHAR(5))');
        $query = $semantics->analyze('SELECT *, (t), 1 FROM s', [$table]);
        $rule = new ColumnTyping();

        self::assertSame('VARCHAR(5)', $rule->field($query->field(0), $query->facts));
        self::assertSame('VARCHAR(5)', $rule->field($query->field(1), $query->facts));
        self::assertNull($rule->field($query->field(2), $query->facts));
    }

    public function testViewTypeRecordsTheCarriedTextOrTheStandardName(): void
    {
        $rule = new ColumnTyping();

        self::assertInstanceOf(NoAffinity::class, $rule->viewType(new DerivedAffinity(), 'VARCHAR(5)'));
        self::assertSame('VARCHAR(5)', $rule->viewType(new DerivedAffinity(Affinity::Text), 'VARCHAR(5)')->name());
        self::assertSame('BLOB', $rule->viewType(new DerivedAffinity(Affinity::Blob), 'VARCHAR(5)')->name());
        self::assertSame('NUM', $rule->viewType(new DerivedAffinity(Affinity::Numeric, true), 'DECIMAL')->name());
        self::assertSame('DECIMAL', $rule->viewType(new DerivedAffinity(Affinity::Numeric), 'DECIMAL')->name());
        self::assertSame('INT', $rule->viewType(new DerivedAffinity(Affinity::Integer), null)->name());
        self::assertSame('REAL', $rule->viewType(new DerivedAffinity(Affinity::Real), 'INT')->name());
        self::assertSame('TEXT', $rule->viewType(new DerivedAffinity(Affinity::Text), null)->name());
        $empty = $rule->viewType(new DerivedAffinity(Affinity::Numeric), '');
        self::assertInstanceOf(ColumnDomain::class, $empty);
        self::assertTrue($empty->typed());
        self::assertSame(Affinity::Numeric, $empty->affinity);
    }

    public function testTableTypeWritesTheAffinityAlone(): void
    {
        $rule = new ColumnTyping();

        self::assertSame('', $rule->tableType(new DerivedAffinity())->name());
        self::assertSame('', $rule->tableType(new DerivedAffinity(Affinity::Blob))->name());
        self::assertFalse($rule->tableType(new DerivedAffinity(Affinity::Blob))->typed());
        self::assertSame('INT', $rule->tableType(new DerivedAffinity(Affinity::Integer))->name());
        self::assertSame('TEXT', $rule->tableType(new DerivedAffinity(Affinity::Text))->name());
        self::assertSame('REAL', $rule->tableType(new DerivedAffinity(Affinity::Real))->name());
        self::assertSame('NUM', $rule->tableType(new DerivedAffinity(Affinity::Numeric))->name());
        self::assertSame('NUM', $rule->tableType(new DerivedAffinity(Affinity::Numeric, true))->name());
    }
}
