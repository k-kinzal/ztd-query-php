<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query\Structure;

use Closure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Statement\Account\Password;
use SqlSemantics\Platform\MySql\Statement\Account\PasswordFunction;
use SqlSemantics\Platform\MySql\Statement\Call\Json\JsonValueCall;
use SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightLevel;
use SqlSemantics\Platform\MySql\Statement\Call\Weight\WeightString;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\ScalarSubquery;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Literal\Parameter;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\ProcedureAnalyse;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Query\SelectOption;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator;
use SqlSemantics\Platform\MySql\Statement\Query\ValueRow;
use SqlSemantics\Platform\MySql\Statement\Query\ValuesQuery;
use SqlSemantics\Platform\MySql\Statement\Query\With\CommonTableExpression;
use SqlSemantics\Platform\MySql\Statement\Query\With\With;
use SqlSemantics\Platform\MySql\Statement\Relation\Join\JoinOperator;
use SqlSemantics\Platform\MySql\Statement\Relation\JoinedTable;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Platform\MySql\Statement\Table\ParseGeneratedColumn;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\PasswordAssignment;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\UserAssignment;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Node;

#[CoversClass(Generations::class)]
#[Small]
final class GenerationsTest extends TestCase
{
    /**
     * @return iterable<string, array{object, int}>
     */
    public static function providerRequirements(): iterable
    {
        yield 'ordinary literal' => [new NumberLiteral('1'), 0];
        yield 'ordinary union' => [SetOperator::Union, 0];
        yield 'procedure' => [new ProcedureAnalyse(), 1];
        yield 'cache' => [SelectOption::Cache, 1];
        yield 'window' => [new WindowSpec(), 2];
        yield 'intersect' => [SetOperator::Intersect, 2];
        yield 'except' => [SetOperator::Except, 2];
        yield 'leading column dot' => [new ColumnUse(new Name('a'), new QualifiedName(new Name('t')), \SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written), 1];
        yield 'leading table dot' => [new TableReference(new QualifiedName(new Name('t')), dot: \SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written), 1];
        yield 'legacy explain modifier' => [\SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainModifier::Partitions, 1];
    }

    #[DataProvider('providerRequirements')]
    public function testRequirementDescribesTheFeatureGeneration(object $value, int $expected): void
    {
        self::assertSame($expected, (new Generations())->requirement($value));
    }

    /**
     * @return iterable<string, array{Closure(): Node}>
     */
    public static function providerConflicts(): iterable
    {
        yield 'generated column and modern table' => [static fn (): Node => new ParseGeneratedColumn(new ScalarSubquery(new ValuesQuery([new ValueRow([new NumberLiteral('1')])])))];
        yield 'weight levels in a modern row' => [static fn (): Node => new ValuesQuery([new ValueRow([new WeightString(new NumberLiteral('1'), levels: [new WeightLevel(new Numeral('1'))])])])];
        yield 'legacy password and modern assignment' => [static fn (): Node => new SetVariables([new PasswordAssignment(new Password(new Text('pw'), PasswordFunction::Password)), new UserAssignment(new UserVariable(new Name('x')), new JsonValueCall(new StringLiteral(['{}']), new StringLiteral(['$.x'])))])];
        yield 'with and procedure' => [static fn (): Node => new QueryExpression(
            new With(false, [new CommonTableExpression(new Name('c'), [], new Select([], [new SelectExpression(new NumberLiteral('1'))]))]),
            new Select([], [new SelectExpression(new NumberLiteral('2'))], procedure: new ProcedureAnalyse()),
        )];
        yield 'procedure in common table' => [static fn (): Node => new CommonTableExpression(new Name('c'), [], new Select([], [new SelectExpression(new NumberLiteral('1'))], procedure: new ProcedureAnalyse()))];
        yield 'modern query in legacy projection' => [static fn (): Node => new Select([SelectOption::Cache], [new SelectExpression(new ScalarSubquery(new ValuesQuery([new ValueRow([new NumberLiteral('1')])])))])];
        yield 'legacy query in modern rows' => [static fn (): Node => new ValuesQuery([new ValueRow([new ScalarSubquery(new Select([], [new SelectExpression(new NumberLiteral('1'))], procedure: new ProcedureAnalyse()))])])];
        yield 'qualify and procedure' => [static fn (): Node => new Select([], [new SelectExpression(new NumberLiteral('1'))], qualify: new NumberLiteral('1'), procedure: new ProcedureAnalyse())];
        yield 'except and unparenthesized limit' => [static fn (): Node => new \SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation(new Select([], [new SelectExpression(new NumberLiteral('1'))], limit: new \SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit(new NumberLiteral('1'))), SetOperator::Except, null, new Select([], [new SelectExpression(new NumberLiteral('2'))]))];
        yield 'modern value with leading column dot' => [static fn (): Node => new ValuesQuery([new ValueRow([new ColumnUse(new Name('a'), new QualifiedName(new Name('t')), \SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written)])])];
        yield 'legacy explain modifier and modern query' => [static fn (): Node => new \SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain(new ValuesQuery([new ValueRow([new NumberLiteral('1')])]), modifier: \SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainModifier::Partitions)];
        yield 'modern with and dotted table' => [static fn (): Node => new QueryExpression(new With(false, [new CommonTableExpression(new Name('c'), [], new Select([], [new SelectExpression(new NumberLiteral('1'))]))]), new Select([], [new SelectExpression(new NumberLiteral('1'))], from: new TableReference(new QualifiedName(new Name('t')), dot: \SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written)))];
        yield 'disjoint sibling subqueries in a scalar root' => [static fn (): Node => new Comparison(
            ComparisonOperator::Equal,
            new ScalarSubquery(new Select([], [new SelectExpression(new NumberLiteral('1'))], procedure: new ProcedureAnalyse())),
            new ScalarSubquery(new ValuesQuery([new ValueRow([new NumberLiteral('1')])])),
        )];
    }

    /**
     * @param Closure(): Node $construct
     */
    #[DataProvider('providerConflicts')]
    public function testCheckRejectsCrossGenerationGraphsDuringConstruction(Closure $construct): void
    {
        $this->expectExceptionMessage('A query cannot combine features removed before MySQL 8.0 with features introduced in MySQL 8.0 or later.');

        $construct();
    }

    public function testCheckAllowsAnEntirelyLegacyGraph(): void
    {
        $query = new Select([SelectOption::Cache], [new SelectExpression(new NumberLiteral('1'))], procedure: new ProcedureAnalyse());
        (new Generations())->check($query);

        self::assertSame(SelectOption::Cache, $query->options[0]);
    }

    public function testCheckAllowsAnEntirelyModernGraph(): void
    {
        $body = new ValuesQuery([new ValueRow([new NumberLiteral('1')])]);
        $query = new QueryExpression(new With(false, [new CommonTableExpression(new Name('c'), [], new Select([], [new SelectExpression(new NumberLiteral('2'))]))]), $body);
        (new Generations())->check($query);

        self::assertSame($body, $query->body);
    }
    public function testCheckRefusesConflictingSpellingProfilesAcrossItems(): void
    {
        $expression = new Arithmetic(ArithmeticOperator::Plus, new NumberLiteral('1'), new NumberLiteral('2'));
        $layout = new \SqlSemantics\Statement\Spelling\Layout([new \SqlSemantics\Statement\Spelling\Spelled('', '1'), new \SqlSemantics\Statement\Spelling\Spelled('', '+'), new \SqlSemantics\Statement\Spelling\Spelled('', '2')]);
        $ordinary = new SelectExpression($expression, layout: $layout);
        $ansi = new SelectExpression(new Arithmetic(ArithmeticOperator::Plus, new NumberLiteral('1'), new NumberLiteral('2')), layout: $layout, spellingProfile: new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::MySql847, new \SqlSemantics\Contract\LexicalSettings('ANSI_QUOTES')));

        $this->expectExceptionMessage('one compatible language profile');

        new Select([], [$ordinary, $ansi]);
    }

    public function testCheckRefusesAnOperatorConflictingWithAnotherItemsSpelling(): void
    {
        $expression = new Arithmetic(ArithmeticOperator::Plus, new NumberLiteral('1'), new NumberLiteral('2'));
        $layout = new \SqlSemantics\Statement\Spelling\Layout([new \SqlSemantics\Statement\Spelling\Spelled('', '1'), new \SqlSemantics\Statement\Spelling\Spelled('', '+'), new \SqlSemantics\Statement\Spelling\Spelled('', '2')]);
        $ordinary = new SelectExpression($expression, layout: $layout);
        $concat = new SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Expression\Operator\Concatenation(new StringLiteral(['a']), new StringLiteral(['b'])));

        $this->expectExceptionMessage('both enabled and disabled PIPES_AS_CONCAT');

        new Select([], [$ordinary, $concat]);
    }

    /**
     * @return iterable<string, array{Closure(): Node}>
     */
    public static function providerSharedOccurrences(): iterable
    {
        yield 'arithmetic literal' => [static function (): Node {
            $literal = new NumberLiteral('1');

            return new Arithmetic(ArithmeticOperator::Plus, $literal, $literal);
        }];
        yield 'parameter' => [static function (): Node {
            $parameter = new Parameter('?');

            return new Arithmetic(ArithmeticOperator::Plus, $parameter, $parameter);
        }];
        yield 'separate wrappers' => [static function (): Node {
            $literal = new NumberLiteral('1');

            return new Arithmetic(ArithmeticOperator::Plus, new Grouped($literal), new Grouped($literal));
        }];
        yield 'select item' => [static function (): Node {
            $item = new SelectExpression(new NumberLiteral('1'));

            return new Select([], [$item, $item]);
        }];
        yield 'subquery' => [static function (): Node {
            $query = new Select([], [new SelectExpression(new NumberLiteral('1'))]);

            return new Select([], [new SelectExpression(new ScalarSubquery($query)), new SelectExpression(new ScalarSubquery($query))]);
        }];
        yield 'table reference' => [static function (): Node {
            $table = new TableReference(new QualifiedName(new Name('t')));

            return new Select([], [new SelectExpression(new NumberLiteral('1'))], from: new JoinedTable($table, JoinOperator::Join, $table));
        }];
    }

    /**
     * @param Closure(): Node $construct The public constructor composition
     */
    #[DataProvider('providerSharedOccurrences')]
    public function testCheckRejectsSharedOccurrencesBeforeDerivation(Closure $construct): void
    {
        $this->expectExceptionMessage('A statement node occurs at one position only.');

        $construct();
    }

    public function testCheckAllowsSharedNamesAndEnumValues(): void
    {
        $name = new Name('a');
        $query = new Select([SelectOption::All, SelectOption::All], [new SelectExpression(new ColumnUse($name), $name), new SelectExpression(new ColumnUse($name), $name)]);

        (new Generations())->check($query);

        self::assertInstanceOf(SelectExpression::class, $query->items[0]);
        self::assertInstanceOf(SelectExpression::class, $query->items[1]);
        self::assertSame($name, $query->items[0]->alias);
        self::assertSame($name, $query->items[1]->alias);
    }

    public function testCheckAllowsOneOccurrenceToBeUsedInIndependentStatements(): void
    {
        $literal = new NumberLiteral('1');
        $first = new Select([], [new SelectExpression($literal)]);
        $second = new Select([], [new SelectExpression($literal)]);

        self::assertInstanceOf(SelectExpression::class, $first->items[0]);
        self::assertInstanceOf(SelectExpression::class, $second->items[0]);
        self::assertSame($first->items[0]->expression, $second->items[0]->expression);
    }
}
