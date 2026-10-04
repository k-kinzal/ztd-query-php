<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ColumnUse;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Binary;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\OutputOrdinal;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\SortTerm;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Query\SetQuantifier;
use SqlSemantics\Platform\Sqlite\Statement\Query\Star;
use SqlSemantics\Platform\Sqlite\Statement\Relation\TableInput;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Reference\Column\AliasTarget;
use SqlSemantics\Statement\Reference\Column\MissingColumn;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Statement\Shape\OpenStar;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(Select::class)]
#[Medium]
final class SelectTest extends TestCase
{
    public function testInputIsTheFromRelation(): void
    {
        $statement = (new Semantics(Dialect::Sqlite))->analyze('SELECT a FROM t')->statement;

        self::assertInstanceOf(Select::class, $statement);
        self::assertInstanceOf(TableInput::class, $statement->input());
        self::assertSame('t', $statement->input()->name->name->value);
        self::assertSame($statement->from, $statement->input());
    }

    public function testInputIsNullWithoutFrom(): void
    {
        $statement = (new Semantics(Dialect::Sqlite))->analyze('SELECT 1')->statement;

        self::assertInstanceOf(Select::class, $statement);
        self::assertNull($statement->input());
    }

    public function testDeriveStatementRecordsTheOutputFields(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $create = $semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)');
        $query = $semantics->analyze('SELECT a, b AS x, a + 1 FROM t', [$create]);

        self::assertCount(3, $query->fields() ?? []);
        self::assertSame('a', $query->field(0)->name?->value);
        self::assertSame('x', $query->field(1)->name?->value);
        self::assertNull($query->field(2)->name);
        self::assertSame($create->declarations()[0]->columns[0], $query->field('a')->column());
        self::assertSame(Nullability::Nullable, $query->field('x')->nullability);
        self::assertSame([], $query->facts->diagnostics);
    }

    public function testDeriveStatementReportsRaiseOutsideATrigger(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('SELECT RAISE(IGNORE)');

        self::assertInstanceOf(Misuse::class, $query->facts->diagnostics[0]);
        self::assertSame(MisuseRule::RaiseOutsideTrigger, $query->facts->diagnostics[0]->rule);
    }

    public function testDeriveQueryLetsOrderByPreferABareAlias(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $create = $semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)');
        $query = $semantics->analyze('SELECT a AS b, b AS a FROM t ORDER BY a', [$create]);

        self::assertInstanceOf(Select::class, $query->statement);
        $resolution = $query->facts->scalar($query->statement->orderBy[0]->expression)->resolution;
        self::assertInstanceOf(AliasTarget::class, $resolution);
        self::assertSame(1, $resolution->field->position);
        self::assertSame($query->field('a'), $resolution->field);
    }

    public function testDeriveQueryLetsWhereSeeInputColumnsBeforeAliases(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $create = $semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)');
        $query = $semantics->analyze('SELECT a AS c, b AS a FROM t WHERE c > 1 AND a > 1', [$create]);

        self::assertInstanceOf(Select::class, $query->statement);
        $where = $query->statement->where;
        self::assertInstanceOf(Binary::class, $where);
        self::assertInstanceOf(Binary::class, $where->left);
        self::assertInstanceOf(Binary::class, $where->right);
        $alias = $query->facts->scalar($where->left->left)->resolution;
        $column = $query->facts->scalar($where->right->left)->resolution;
        self::assertInstanceOf(AliasTarget::class, $alias);
        self::assertSame('c', $alias->field->name?->value);
        self::assertInstanceOf(ResolvedColumn::class, $column);
        self::assertSame($create->declarations()[0]->columns[0], $column->declaration());
    }

    public function testDeriveQueryKeepsResultColumnsFromSeeingEachOther(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $create = $semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)');
        $query = $semantics->analyze('SELECT a AS c, c FROM t', [$create]);

        self::assertInstanceOf(MissingColumn::class, $query->field(1)->resolution);
        self::assertSame('Column c does not exist.', $query->facts->diagnostics[0]->message());
    }

    public function testDeriveQueryMakesEveryInputColumnNullableInAnAggregateQueryWithoutGroupBy(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $create = $semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)');
        $aggregate = $semantics->analyze('SELECT count(*), a FROM t', [$create]);
        $grouped = $semantics->analyze('SELECT count(*), a FROM t GROUP BY a', [$create]);
        $window = $semantics->analyze('SELECT count(*) OVER (), a FROM t', [$create]);

        self::assertSame(Nullability::Nullable, $aggregate->field(1)->nullability);
        self::assertSame(Nullability::NotNull, $grouped->field(1)->nullability);
        self::assertSame(Nullability::NotNull, $window->field(1)->nullability);
    }

    public function testDeriveQueryReportsAStarWithoutInput(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('SELECT *');

        self::assertCount(0, $query->fields() ?? []);
        self::assertSame([], $query->shape()?->slots);
        self::assertSame('no tables specified', $query->facts->diagnostics[0]->message());
    }

    public function testDeriveQueryReportsARowValueInEveryClauseThatNeedsASingleValue(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $create = $semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)');
        $query = $semantics->analyze('SELECT (a, b) FROM t WHERE (a, b) GROUP BY (a, b) HAVING (a, b) ORDER BY (a, b)', [$create]);

        self::assertCount(5, $query->facts->diagnostics);
        self::assertSame(['row value misused'], array_unique(array_map(static fn (object $problem): string => $problem->message(), $query->facts->diagnostics)));
    }

    public function testDeriveQueryGivesAnUndeclaredStarAnOpenProjection(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('SELECT a, * FROM t');

        self::assertNull($query->fields());
        self::assertInstanceOf(OpenStar::class, $query->facts->output?->projection[1]);
        self::assertSame('the declaration of relation t', $query->facts->output->projection[1]->missing[0]->describe());
        self::assertFalse($query->shape()?->complete());
    }

    public function testDeriveQueryKeepsLimitFromSeeingTheColumns(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $create = $semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)');
        $query = $semantics->analyze('SELECT a FROM t LIMIT a', [$create]);

        self::assertInstanceOf(Select::class, $query->statement);
        self::assertNotNull($query->statement->limit);
        self::assertInstanceOf(MissingColumn::class, $query->facts->scalar($query->statement->limit->count)->resolution);
    }

    public function testRenderWritesEveryClauseInGrammarOrder(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('select distinct a, count(*) as n from t where a > 1 group by a having n > 1 window w as (partition by a) order by n desc limit 3 offset 1');

        self::assertSame('SELECT DISTINCT a, count(*) AS n FROM t WHERE a > 1 GROUP BY a HAVING n > 1 WINDOW w AS (PARTITION BY a) ORDER BY n DESC LIMIT 3 OFFSET 1', $query->toString());
    }

    public function testRenderWritesANewlyBuiltSelection(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $select = new Select([new ResultColumn(new ColumnUse(new Name('a')), new Name('x')), new Star()], new TableInput(new QualifiedName(new Name('t'))), null, [new ColumnUse(new Name('a'))], null, [], [new SortTerm(new OutputOrdinal(new IntegerLiteral('1')))], null, SetQuantifier::All);
        $operation = new Operation($semantics->context(), $select);

        self::assertSame('SELECT ALL a AS x, * FROM t GROUP BY a ORDER BY 1', $operation->toString());
        self::assertSame(SetQuantifier::All, $select->quantifier);
    }

    public function testRenderRefusesAnIntegerConstantAsAnOrdinaryOrderByTerm(): void
    {
        $this->expectExceptionMessage('An integer constant in ORDER BY or GROUP BY is a result column position.');

        new Select([new Star()], null, null, [], null, [], [new SortTerm(new IntegerLiteral('1'))]);
    }

    public function testRenderRefusesAnIntegerConstantAsAnOrdinaryGroupByTerm(): void
    {
        $this->expectExceptionMessage('An integer constant in ORDER BY or GROUP BY is a result column position.');

        new Select([new Star()], null, null, [new IntegerLiteral('2')]);
    }

    public function testRenderRefusesAnEmptyColumnList(): void
    {
        $this->expectExceptionMessage('A selection projects at least one result column: an expression, a star or a qualified star.');

        new Select([]);
    }

    public function testDeriveQueryTypesAConstantResultColumn(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze("SELECT 1 AS n, 'x' AS s");
        $type = $query->field('n')->type;

        self::assertInstanceOf(Known::class, $type);
        self::assertSame(Storage::Integer, $type->descriptor);
        self::assertSame(Nullability::NotNull, $query->field('s')->nullability);
    }
}
