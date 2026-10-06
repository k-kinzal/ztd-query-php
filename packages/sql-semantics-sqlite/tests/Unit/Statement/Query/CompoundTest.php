<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\NullLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Query\Compound;
use SqlSemantics\Platform\Sqlite\Statement\Query\CompoundOperator;
use SqlSemantics\Platform\Sqlite\Statement\Query\CompoundStep;
use SqlSemantics\Platform\Sqlite\Statement\Query\Limit;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\SortTerm;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\ArityMismatch;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\ArityRule;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Query\ValueRow;
use SqlSemantics\Platform\Sqlite\Statement\Query\ValuesClause;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Reference\Column\AliasTarget;
use SqlSemantics\Statement\Shape\OpenStar;
use SqlSemantics\Statement\Type\Choice;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(Compound::class)]
#[Medium]
final class CompoundTest extends TestCase
{
    public function testDeriveStatementNamesTheOutputAfterTheFirstArm(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze("SELECT 1 AS a, 'x' AS b UNION ALL SELECT 2 AS c, 'y' AS d");

        self::assertInstanceOf(Compound::class, $query->statement);
        self::assertSame(['a', 'b'], array_map(static fn (object $field): ?string => $field->name?->value, [...$query->fields() ?? []]));
        self::assertSame([], $query->facts->diagnostics);
    }

    public function testDeriveQueryChoosesTheTypeOverTheArmsAndTheirNullability(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze("SELECT 1 AS a UNION SELECT 'x' UNION SELECT NULL");
        $type = $query->field('a')->type;

        self::assertInstanceOf(Choice::class, $type);
        self::assertSame([Storage::Integer, Storage::Text], $type->alternatives);
        self::assertSame(Nullability::Nullable, $query->field('a')->nullability);
    }

    public function testDeriveQueryReportsArmsOfDifferentWidthOnce(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('SELECT 1 UNION SELECT 2, 3 UNION SELECT 4, 5, 6');

        self::assertCount(1, $query->facts->diagnostics);
        self::assertInstanceOf(ArityMismatch::class, $query->facts->diagnostics[0]);
        self::assertSame('SELECTs to the left and right of a compound operator do not have the same number of result columns: 1 and 2.', $query->facts->diagnostics[0]->message());
        self::assertSame(ArityRule::CompoundArms, $query->facts->diagnostics[0]->rule);
        self::assertCount(1, $query->fields() ?? []);
    }

    public function testDeriveQueryOrdersTheCombinedRowsByTheNamesOfEveryArm(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('SELECT 1 AS a UNION ALL SELECT 2 AS b ORDER BY b');

        self::assertInstanceOf(Compound::class, $query->statement);
        self::assertInstanceOf(Select::class, $query->statement->steps[0]->query);
        self::assertSame([], $query->statement->steps[0]->query->orderBy);
        $resolution = $query->facts->scalar($query->statement->orderBy[0]->expression)->resolution;
        self::assertInstanceOf(AliasTarget::class, $resolution);
        self::assertSame(0, $resolution->field->position);
        self::assertSame('b', $resolution->field->name?->value);
    }

    public function testDeriveQueryReportsAnOrderByOrALimitBeforeTheOperator(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $ordered = $semantics->analyze('SELECT 1 ORDER BY 1 UNION SELECT 2');
        $limited = $semantics->analyze('SELECT 1 LIMIT 1 UNION SELECT 2');

        self::assertInstanceOf(Misuse::class, $ordered->facts->diagnostics[0]);
        self::assertSame(MisuseRule::OrderByBeforeCompound, $ordered->facts->diagnostics[0]->rule);
        self::assertInstanceOf(Misuse::class, $limited->facts->diagnostics[0]);
        self::assertSame(MisuseRule::LimitBeforeCompound, $limited->facts->diagnostics[0]->rule);
    }

    public function testDeriveQueryKeepsAnUndeclaredFirstArmOpen(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('SELECT * FROM t UNION SELECT 1');

        self::assertNull($query->fields());
        self::assertInstanceOf(OpenStar::class, $query->facts->output?->projection[0]);
    }

    public function testRenderWritesTheArmsThenTheOrderingAndTheLimit(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('select 1 as a union all values (2) intersect select 3 except select 4 order by a desc limit 1 offset 1');

        self::assertSame('SELECT 1 AS a UNION ALL VALUES (2) INTERSECT SELECT 3 EXCEPT SELECT 4 ORDER BY a DESC LIMIT 1 OFFSET 1', $query->toString());
    }

    public function testRenderWritesANewlyBuiltCompound(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $first = new ValuesClause([new ValueRow([new IntegerLiteral('1')])]);
        $compound = new Compound($first, [new CompoundStep(CompoundOperator::Union, new Select([new ResultColumn(new IntegerLiteral('2'))]))], [], new Limit(new IntegerLiteral('1')));
        $operation = new Operation($semantics->context(), $compound);

        self::assertSame('VALUES (1) UNION SELECT 2 LIMIT 1', $operation->toString());
        self::assertSame('column1', $operation->field(0)->name?->value);
    }

    public function testRenderRefusesAnOrderingLeftOnTheLastArm(): void
    {
        $ordered = (new Semantics(Dialect::Sqlite))->analyze('SELECT 1 AS a ORDER BY a')->statement;
        self::assertInstanceOf(Select::class, $ordered);

        $this->expectExceptionMessage('ORDER BY and LIMIT after the last arm belong to the compound query.');

        new Compound(new ValuesClause([new ValueRow([new NullLiteral()])]), [new CompoundStep(CompoundOperator::Union, $ordered)]);
    }

    public function testRenderRefusesAnOrderingAfterAValuesArm(): void
    {
        $this->expectExceptionMessage('ORDER BY and LIMIT cannot follow a VALUES clause.');

        new Compound(new Select([new ResultColumn(new IntegerLiteral('1'))]), [new CompoundStep(CompoundOperator::Union, new ValuesClause([new ValueRow([new NullLiteral()])]))], [], new Limit(new IntegerLiteral('1')));
    }

    public function testRenderRefusesAnIntegerConstantAsAnOrdinaryOrderByTerm(): void
    {
        $this->expectExceptionMessage('An integer constant in ORDER BY is a result column position.');

        new Compound(new Select([new ResultColumn(new IntegerLiteral('1'))]), [new CompoundStep(CompoundOperator::Union, new Select([new ResultColumn(new IntegerLiteral('2'))]))], [new SortTerm(new IntegerLiteral('1'))]);
    }

    public function testRenderRefusesASingleArm(): void
    {
        $this->expectExceptionMessage('A compound query has at least two arms.');

        new Compound(new Select([new ResultColumn(new IntegerLiteral('1'))]), []);
    }
}
