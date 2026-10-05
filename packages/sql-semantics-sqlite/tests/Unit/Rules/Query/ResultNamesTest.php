<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Rules\Query\ResultNames;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Collate;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ColumnUse;
use SqlSemantics\Platform\Sqlite\Statement\Expression\DoubleQuotedWord;
use SqlSemantics\Platform\Sqlite\Statement\Expression\FunctionCall;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Grouped;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Binary;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\BinaryOperator;
use SqlSemantics\Platform\Sqlite\Statement\Expression\TruthWord;
use SqlSemantics\Platform\Sqlite\Statement\Query\Compound;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Query\ValuesClause;
use SqlSemantics\Platform\Sqlite\Statement\Query\With\WithQuery;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Column\ConditionalColumn;
use SqlSemantics\Statement\Spelling\Layout;
use SqlSemantics\Statement\Spelling\Spelled;

#[CoversClass(ResultNames::class)]
#[Medium]
final class ResultNamesTest extends TestCase
{
    public function testSpanAnswersTheLayoutTextOrTheCanonicalRendering(): void
    {
        $names = new ResultNames();
        $sum = new Binary(BinaryOperator::Add, new IntegerLiteral('1'), new IntegerLiteral('1'));
        $written = new ResultColumn($sum, null, new Layout([new Spelled('', '1'), new Spelled(' /* x */ ', '+'), new Spelled('', '1')]));

        self::assertSame('1 /* x */ +1', $names->span($written)->value);
        self::assertSame('1 + 1', $names->span(new ResultColumn(new Binary(BinaryOperator::Add, new IntegerLiteral('1'), new IntegerLiteral('1'))))->value);
    }

    public function testOutputNamesAColumnByItsAliasItsColumnOrItsText(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $create = $semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)');
        $query = $semantics->analyze('SELECT (t.A), oid, b AS x, 1+1, "zz", true, nosuch, A COLLATE nocase, "a" FROM t', [$create]);

        self::assertSame(['a', 'rowid', 'x', '1+1', '"zz"', 'true', 'nosuch', 'A COLLATE nocase', 'a'], array_map(static fn (int $position): ?string => $query->field($position)->name?->value, range(0, 8)));
    }

    public function testResolvedLeavesAWordConditionalOnAMissingDeclarationUnnamed(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('SELECT "zz", nosuch, true FROM t');
        self::assertInstanceOf(Select::class, $query->statement);
        $column = $query->statement->columns[0];
        self::assertInstanceOf(ResultColumn::class, $column);
        $resolution = $query->field(0)->resolution;

        self::assertInstanceOf(ConditionalColumn::class, $resolution);
        self::assertNull((new ResultNames())->resolved($column, new DoubleQuotedWord(new Name('zz')), $resolution));
        self::assertSame('nosuch', $query->field(1)->name?->value);
        self::assertNull($query->field(2)->name);
        self::assertSame('"zz"', (new ResultNames())->resolved($column, new DoubleQuotedWord(new Name('zz')), null)?->value);
    }

    public function testWrittenAnswersTheOneWordAnExpressionConsistsOf(): void
    {
        $names = new ResultNames();

        self::assertSame('a', $names->written(new ColumnUse(new Name('a'), new QualifiedName(new Name('t'))))?->value);
        self::assertSame('a', $names->written(new Grouped(new Collate(new ColumnUse(new Name('a')), new Name('nocase'))))?->value);
        self::assertSame('zz', $names->written(new DoubleQuotedWord(new Name('zz')))?->value);
        self::assertSame('false', $names->written(new TruthWord(false))?->value);
        self::assertNull($names->written(new IntegerLiteral('1')));
        self::assertNull($names->written(null));
    }

    public function testRelationPrefersTheAliasThenTheWrittenWordThenTheText(): void
    {
        $names = new ResultNames();

        self::assertSame('x', $names->relation(new ResultColumn(new ColumnUse(new Name('a')), new Name('x')))->value);
        self::assertSame('A', $names->relation(new ResultColumn(new Collate(new ColumnUse(new Name('A')), new Name('nocase'))))->value);
        self::assertSame('1', $names->relation(new ResultColumn(new IntegerLiteral('1')))->value);
    }

    public function testDeclaredLooksThroughCollateAndTheLikelihoodFunctions(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $create = $semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)');
        $view = $semantics->analyze('CREATE VIEW v AS SELECT A COLLATE nocase, likely(B), likelihood((a), 0.5), "zz", true, 1+1, a AS x FROM t', [$create]);

        self::assertSame(['a', 'b', 'a:1', '"zz"', 'column5', '1+1', 'x'], array_map(static fn (\SqlSemantics\Statement\Declaration\Column $column): string => $column->name->value, $view->declarations()[0]->columns));
    }

    public function testInnerLooksThroughParenthesesCollateAndTheLikelihoodFunctions(): void
    {
        $names = new ResultNames();
        $a = new ColumnUse(new Name('a'));

        self::assertSame($a, $names->inner(new FunctionCall(new Name('LIKELY'), [$a])));
        self::assertSame($a, $names->inner(new FunctionCall(new Name('likelihood'), [$a, new IntegerLiteral('1')])));
        self::assertSame($a, $names->inner(new Grouped($a)));
        self::assertSame($a, $names->inner(new Collate($a, new Name('nocase'))));
        self::assertNull($names->inner(new FunctionCall(new Name('unlikely'), [])));
        self::assertNull($names->inner(new FunctionCall(new Name('max'), [new ColumnUse(new Name('a'))])));
        self::assertNull($names->inner(new IntegerLiteral('1')));
    }

    public function testLeftmostFindsTheArmThatNamesTheColumns(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $compound = $semantics->analyze('WITH c AS (SELECT 1) VALUES (1) UNION SELECT 2')->statement;
        $select = $semantics->analyze('SELECT 1')->statement;

        self::assertInstanceOf(WithQuery::class, $compound);
        self::assertInstanceOf(Compound::class, $compound->body);
        self::assertInstanceOf(ValuesClause::class, (new ResultNames())->leftmost($compound));
        self::assertSame($compound->body->first, (new ResultNames())->leftmost($compound));
        self::assertInstanceOf(Select::class, $select);
        self::assertSame($select, (new ResultNames())->leftmost($select));
    }

    public function testColumnFindsTheResultColumnOfAFieldAndNoneForAStar(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $create = $semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)');
        $query = $semantics->analyze('SELECT a + 1, * FROM t', [$create]);
        $select = $query->statement;

        self::assertInstanceOf(Select::class, $select);
        self::assertSame($select->columns[0], (new ResultNames())->column($select, $query->field(0)));
        self::assertNull((new ResultNames())->column($select, $query->field(1)));
    }

    public function testTruthRenamesTrueAndFalseAfterThePosition(): void
    {
        $names = new ResultNames();

        self::assertSame('column3', $names->truth(new Name('TRUE'), 2)?->value);
        self::assertSame('column1', $names->truth(new Name('false'), 0)?->value);
        self::assertSame('truth', $names->truth(new Name('truth'), 0)?->value);
        self::assertNull($names->truth(null, 0));
    }
}
