<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query\Structure;

use Closure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\ScalarSubquery;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
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
use SqlSemantics\Statement\Identifier\Name;
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
        yield 'with and procedure' => [static fn (): Node => new QueryExpression(
            new With(false, [new CommonTableExpression(new Name('c'), [], new Select([], [new SelectExpression(new NumberLiteral('1'))]))]),
            new Select([], [new SelectExpression(new NumberLiteral('2'))], procedure: new ProcedureAnalyse()),
        )];
        yield 'procedure in common table' => [static fn (): Node => new CommonTableExpression(new Name('c'), [], new Select([], [new SelectExpression(new NumberLiteral('1'))], procedure: new ProcedureAnalyse()))];
        yield 'modern query in legacy projection' => [static fn (): Node => new Select([SelectOption::Cache], [new SelectExpression(new ScalarSubquery(new ValuesQuery([new ValueRow([new NumberLiteral('1')])])))])];
        yield 'legacy query in modern rows' => [static fn (): Node => new ValuesQuery([new ValueRow([new ScalarSubquery(new Select([SelectOption::Cache], [new SelectExpression(new NumberLiteral('1'))]))])])];
        yield 'qualify and procedure' => [static fn (): Node => new Select([], [new SelectExpression(new NumberLiteral('1'))], qualify: new NumberLiteral('1'), procedure: new ProcedureAnalyse())];
        yield 'disjoint sibling subqueries in a scalar root' => [static fn (): Node => new Comparison(
            ComparisonOperator::Equal,
            new ScalarSubquery(new Select([SelectOption::Cache], [new SelectExpression(new NumberLiteral('1'))])),
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
}
