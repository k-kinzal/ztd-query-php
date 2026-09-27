<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Composition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\Composition\Composition;
use SqlSemantics\Core\CompositionException;
use SqlSemantics\Core\Language;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect as SqliteDialect;
use SqlSemantics\Statement\Declaration\Affinity;
use SqlSemantics\Statement\Declaration\Builtin;
use SqlSemantics\Statement\Declaration\TypeDescriptor;
use SqlSemantics\Statement\Writer;

#[CoversClass(Composition::class)]
#[CoversClass(\SqlSemantics\Core\Composition\Templating::class)]
#[UsesClass(\SqlSemantics\Core\Composition\Templates::class)]
#[UsesClass(\SqlSemantics\Statement\Traversal::class)]
#[UsesClass(TypeDescriptor::class)]
#[UsesClass(\SqlSemantics\Statement\Declaration\Invariant::class)]
#[UsesClass(\SqlSemantics\Platform\Sqlite\Casts::class)]
#[UsesClass(\SqlSemantics\Platform\Sqlite\Expressions::class)]
#[UsesClass(\SqlSemantics\Platform\Sqlite\TypeReader::class)]
#[UsesClass(Semantics::class)]
#[UsesClass(Language::class)]
#[UsesClass(CompositionException::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\Analyzer::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\LeafReader::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\ValueReader::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\Vocabulary::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\TriviaReader::class)]
#[UsesClass(\SqlSemantics\Core\Analysis\SourceComments::class)]
#[UsesClass(\SqlSemantics\Core\Ast\DialectParser::class)]
#[UsesClass(\SqlSemantics\Core\Composition\Operands::class)]
#[UsesClass(\SqlSemantics\Statement\Statement::class)]
#[UsesClass(\SqlSemantics\Statement\Comments::class)]
#[UsesClass(Writer::class)]
#[UsesClass(\SqlSemantics\Statement\Assertion::class)]
#[UsesClass(\SqlSemantics\Statement\ImmutableGraph::class)]
#[UsesClass(\SqlSemantics\Platform\Sqlite\Platform::class)]
#[UsesClass(\SqlSemantics\Platform\Sqlite\Builder::class)]
#[Medium]
final class CompositionTest extends TestCase
{
    public function testFormsAreFoundBySymbolsAndBuiltFromArguments(): void
    {
        $builder = (new Semantics(SqliteDialect::Sqlite))->builder();
        self::assertSame('a AND b', Writer::render($builder->and($builder->column('a'), $builder->column('b'))));
        self::assertSame('a AND( b OR c )', Writer::render($builder->and($builder->column('a'), $builder->or($builder->column('b'), $builder->column('c')))));
        self::assertSame('a OR b OR c', Writer::render($builder->or($builder->or($builder->column('a'), $builder->column('b')), $builder->column('c'))));
    }

    public function testAFormTheReleaseLacksCannotBeComposed(): void
    {
        $semantics = new Semantics(SqliteDialect::Sqlite);
        $builder = $semantics->builder();
        $this->expectException(CompositionException::class);
        $this->expectExceptionMessage('must be a oneselect');
        $builder->unionAll($semantics->analyze('SELECT 1')->command, $builder->unionAll($semantics->analyze('SELECT 2')->command, $semantics->analyze('SELECT 3')->command));
    }

    public function testNamesAreBareWhenTheRoleAdmitsThemAndQuotedOtherwise(): void
    {
        $builder = (new Semantics(SqliteDialect::Sqlite))->builder();
        self::assertSame('plain', Writer::render($builder->identifier('plain')));
        self::assertSame('"with space"', Writer::render($builder->identifier('with space')));
        self::assertSame('"select"', Writer::render($builder->identifier('select')));
        self::assertSame('"9lives"', Writer::render($builder->identifier('9lives')));
    }

    #[TestWith([0.1 + 0.2, '0.30000000000000004'])]
    #[TestWith([123.25, '123.25'])]
    #[TestWith([100.0, '100.0'])]
    #[TestWith([1e20, '100000000000000000000.0'])]
    #[TestWith([1e21, '1e21'])]
    #[TestWith([1e-7, '0.0000001'])]
    #[TestWith([1.5e-8, '1.5e-8'])]
    #[TestWith([5e-324, '5e-324'])]
    #[TestWith([PHP_FLOAT_MAX, '1.7976931348623157e308'])]
    #[TestWith([0.0, '0.0'])]
    #[TestWith([-0.0, '- 0.0'])]
    public function testFloatsAreSpelledWithTheFewestDigitsThatReadBackAsTheNumber(float $value, string $expected): void
    {
        self::assertSame($expected, Writer::render((new Semantics(SqliteDialect::Sqlite))->builder()->float($value)));
    }

    public function testFloatSpellingDoesNotDependOnThePrecisionSettings(): void
    {
        $builder = (new Semantics(SqliteDialect::Sqlite))->builder();
        $precision = ini_set('precision', '3');
        $serialize = ini_set('serialize_precision', '3');
        $spelled = Writer::render($builder->float(0.1 + 0.2));
        ini_set('precision', (string) $precision);
        ini_set('serialize_precision', (string) $serialize);
        self::assertSame('0.30000000000000004', $spelled);
    }

    public function testAnEmptyNameIsRejected(): void
    {
        $this->expectException(CompositionException::class);
        (new Semantics(SqliteDialect::Sqlite))->builder()->identifier('');
    }

    public function testListsFoldAndUnfoldThroughTheirRecursiveForm(): void
    {
        $semantics = new Semantics(SqliteDialect::Sqlite);
        $builder = $semantics->builder();
        $one = $builder->cte('a', $semantics->analyze('SELECT 1')->command);
        $two = $builder->cte('b', $semantics->analyze('SELECT 2')->command);
        $three = $builder->cte('c', $semantics->analyze('SELECT 3')->command);
        $query = $builder->with([$one], $builder->with([$two, $three], $semantics->analyze('SELECT 4')->command));
        self::assertSame('WITH a AS( SELECT 1 ) , b AS( SELECT 2 ) , c AS( SELECT 3 ) SELECT 4', Writer::render($query));
    }

    public function testAStatementEnvelopeIsUnwrappedOnlyWhenItsOtherChildrenWriteNothing(): void
    {
        $semantics = new Semantics(SqliteDialect::Sqlite);
        $builder = $semantics->builder();
        $cte = $builder->cte('a', $semantics->analyze('SELECT 1')->command);
        self::assertSame('WITH a AS( SELECT 1 ) SELECT 2', Writer::render($builder->with([$cte], $semantics->analyze('SELECT 2;')->command)));
        $this->expectException(CompositionException::class);
        $builder->with([$cte], $semantics->analyze('EXPLAIN SELECT 2')->command);
    }

    public function testIsNullParenthesizesAnOperandThatBindsMoreWeakly(): void
    {
        $builder = (new Semantics(SqliteDialect::Sqlite))->builder();
        self::assertSame('( a AND b ) IS NULL', Writer::render($builder->isNull($builder->and($builder->column('a'), $builder->column('b')))));
        self::assertSame('a IS NOT NULL', Writer::render($builder->isNull($builder->column('a'), true)));
    }

    public function testInListsTheValuesInOrder(): void
    {
        $builder = (new Semantics(SqliteDialect::Sqlite))->builder();
        self::assertSame("a IN( 1 , 'x' )", Writer::render($builder->in($builder->column('a'), [$builder->integer(1), $builder->string('x')])));
        self::assertSame('( a OR b ) NOT IN( 1 )', Writer::render($builder->in($builder->or($builder->column('a'), $builder->column('b')), [$builder->integer(1)], true)));
    }

    public function testCaseWritesEachConditionWithItsResultAndTheDefault(): void
    {
        $builder = (new Semantics(SqliteDialect::Sqlite))->builder();
        $case = $builder->case([[$builder->column('a'), $builder->integer(1)], [$builder->column('b'), $builder->integer(2)]], $builder->null());
        self::assertSame('CASE WHEN a THEN 1 WHEN b THEN 2 ELSE NULL END', Writer::render($case));
        self::assertSame('CASE WHEN a THEN 1 END', Writer::render($builder->case([[$builder->column('a'), $builder->integer(1)]])));
    }

    public function testCaseNeedsACondition(): void
    {
        $this->expectException(CompositionException::class);
        (new Semantics(SqliteDialect::Sqlite))->builder()->case([]);
    }

    public function testCallSpellsTheFunctionNameBareOrQuoted(): void
    {
        $builder = (new Semantics(SqliteDialect::Sqlite))->builder();
        self::assertSame('coalesce ( a , 0 )', Writer::render($builder->call('coalesce', [$builder->column('a'), $builder->integer(0)])));
        self::assertSame('"my func" ( )', Writer::render($builder->call('my func')));
    }

    public function testCallNeedsAName(): void
    {
        $this->expectException(CompositionException::class);
        (new Semantics(SqliteDialect::Sqlite))->builder()->call('');
    }

    public function testCastSpellsTheTypeAsTheDatabaseNamesIt(): void
    {
        $builder = (new Semantics(SqliteDialect::Sqlite))->builder();
        self::assertSame('CAST( 1 AS NUMERIC ( 10 , 2 ) )', Writer::render($builder->cast($builder->integer(1), new TypeDescriptor(Builtin::Numeric, precision: 10, scale: 2))));
    }

    public function testCastRejectsAFactTheTargetCannotState(): void
    {
        $this->expectException(CompositionException::class);
        $this->expectExceptionMessage('cannot state its arrayDimensions');
        $builder = (new Semantics(SqliteDialect::Sqlite))->builder();
        $builder->cast($builder->integer(1), new TypeDescriptor(Builtin::Integer, arrayDimensions: 1, affinity: Affinity::Integer));
    }

    public function testSelectComposesACompleteQueryOfAliasedColumns(): void
    {
        $semantics = new Semantics(SqliteDialect::Sqlite);
        $builder = $semantics->builder();
        $query = $builder->select([[$builder->integer(1), 'id'], [$builder->string('a'), 'select'], [$builder->column('x'), null]], $builder->table('main', 'users'), $builder->or($builder->column('a'), $builder->column('b')));
        self::assertSame('SELECT 1 AS id , \'a\' AS "select" , x FROM main.users WHERE a OR b', Writer::render($query));
        self::assertInstanceOf(\SqlSemantics\Statement\Command::class, $query);
        self::assertSame(Writer::render($query), $semantics->analyze(Writer::render($query))->toString());
    }

    public function testSelectNeedsAColumn(): void
    {
        $this->expectException(CompositionException::class);
        (new Semantics(SqliteDialect::Sqlite))->builder()->select([]);
    }
}
