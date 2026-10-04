<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Definition\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Rules\Definition\Query\DerivedAffinity;
use SqlSemantics\Platform\Sqlite\Rules\Definition\Query\ValueKinds;
use SqlSemantics\Platform\Sqlite\Statement\Expression\CaseExpression;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Query\ValuesClause;
use SqlSemantics\Platform\Sqlite\Statement\Type\Affinity;

#[CoversClass(ValueKinds::class)]
#[Medium]
final class ValueKindsTest extends TestCase
{
    public function testOfClassifiesEachKindOfExpression(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (a TEXT, b INTEGER, c)');
        $query = $semantics->analyze("SELECT NULL, 'x', x'00', a || b, ?, abs(1), CURRENT_TIME, CASE WHEN 1 THEN 'x' ELSE 1 END, CASE WHEN 1 THEN NULL END, a, b, c, CAST(1 AS TEXT), (SELECT b FROM t), (a, b), \"zz\", +(a), a COLLATE nocase, (b), 1, TRUE, EXISTS (SELECT 1), -1, zz FROM t", [$table]);
        $select = $query->statement;
        $rule = new ValueKinds();

        self::assertInstanceOf(Select::class, $select);
        $kinds = array_map(static fn (int $position): ?int => $rule->arm($select, $position, $query->facts), range(0, 23));
        self::assertSame([0, 2, 4, 2, 7, 7, 7, 3, 0, 6, 5, 7, 6, 5, 6, 2, 6, 6, 5, 1, 1, 1, 1, null], $kinds);
    }

    public function testResultsUnitesTheKindsOfEveryResultOfACase(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (a TEXT)');
        $query = $semantics->analyze("SELECT CASE a WHEN 1 THEN x'00' WHEN 2 THEN 1 ELSE a END, CASE WHEN 1 THEN zz END FROM t", [$table]);
        $select = $query->statement;
        $rule = new ValueKinds();

        self::assertInstanceOf(Select::class, $select);
        $first = $select->columns[0];
        $second = $select->columns[1];
        self::assertInstanceOf(ResultColumn::class, $first);
        self::assertInstanceOf(ResultColumn::class, $second);
        self::assertInstanceOf(CaseExpression::class, $first->expression);
        self::assertInstanceOf(CaseExpression::class, $second->expression);
        self::assertSame(ValueKinds::NUMBER | ValueKinds::TEXT | ValueKinds::BLOB, $rule->results($first->expression, $query->facts));
        self::assertNull($rule->results($second->expression, $query->facts));
    }

    public function testAffinedReadsTheKindsFromAnAffinity(): void
    {
        $rule = new ValueKinds();

        self::assertSame(ValueKinds::NUMBER | ValueKinds::BLOB, $rule->affined(new DerivedAffinity(Affinity::Integer)));
        self::assertSame(ValueKinds::NUMBER | ValueKinds::BLOB, $rule->affined(new DerivedAffinity(Affinity::Numeric, true)));
        self::assertSame(ValueKinds::TEXT | ValueKinds::BLOB, $rule->affined(new DerivedAffinity(Affinity::Text)));
        self::assertSame(ValueKinds::NUMBER | ValueKinds::TEXT | ValueKinds::BLOB, $rule->affined(new DerivedAffinity(Affinity::Blob)));
        self::assertSame(ValueKinds::NUMBER | ValueKinds::TEXT | ValueKinds::BLOB, $rule->affined(new DerivedAffinity()));
        self::assertNull($rule->affined(new DerivedAffinity(null, false, false)));
    }

    public function testArmReadsAnExpandedStarAndAnAbsentPosition(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = $semantics->analyze('CREATE TABLE t (a TEXT)');
        $query = $semantics->analyze('SELECT * FROM t', [$table]);
        $rows = $semantics->analyze("VALUES (1, 'a')");
        $values = $rows->statement;
        $select = $query->statement;
        $rule = new ValueKinds();

        self::assertInstanceOf(Select::class, $select);
        self::assertInstanceOf(ValuesClause::class, $values);
        self::assertSame(ValueKinds::TEXT | ValueKinds::BLOB, $rule->arm($select, 0, $query->facts));
        self::assertNull($rule->arm($select, 1, $query->facts));
        self::assertSame(ValueKinds::TEXT, $rule->arm($values->rows[0], 1, $rows->facts));
        self::assertNull($rule->arm($values->rows[0], 2, $rows->facts));
    }
}
