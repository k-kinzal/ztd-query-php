<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Query\Structure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Server\DeferredExplain;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(ReleaseDomain::class)]
#[Small]
final class ReleaseDomainTest extends TestCase
{
    public function testHandlerKeepsCallsInTheirNativeReleases(): void
    {
        $call = new \SqlSemantics\Platform\MySql\Statement\Call\FunctionCall(new Name('X'), [new \SqlSemantics\Platform\MySql\Statement\Call\CallArgument(new NumberLiteral('1'))]);
        $read = new \SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerScan(new \SqlSemantics\Platform\MySql\Statement\Dml\Handler\OpenHandler(new Name('h')), \SqlSemantics\Platform\MySql\Statement\Dml\Handler\ScanDirection::First, $call);
        self::assertSame(3, (new ReleaseDomain())->of($read));
        self::assertSame(3, (new ReleaseDomain())->handler($read));
        self::assertSame(511, (new ReleaseDomain())->handler(new NumberLiteral('1')));
    }

    public function testOfKeepsRetainedPartitionSubqueriesInLegacyDefinitions(): void
    {
        $query = new \SqlSemantics\Platform\MySql\Statement\Expression\Subquery\ScalarSubquery(new Select([], [new SelectExpression(new NumberLiteral('1'))]));
        $method = new \SqlSemantics\Platform\MySql\Statement\Partition\Method\DeferredMethod($query);
        self::assertSame(3, (new ReleaseDomain())->of($method));
        self::assertSame(511, (new ReleaseDomain())->of(new \SqlSemantics\Platform\MySql\Statement\Partition\Method\HashMethod(false, new NumberLiteral('1'))));
    }

    public function testOrderingSeparatesBooleanConstantsFromLegacyPositions(): void
    {
        $literal = new \SqlSemantics\Platform\MySql\Statement\Literal\BooleanLiteral(true);
        $select = new Select([], [new SelectExpression(new NumberLiteral('1'))], orderBy: [new \SqlSemantics\Platform\MySql\Statement\Query\OrderItem($literal)]);
        self::assertSame(508, (new ReleaseDomain())->ordering($select));
        self::assertSame(508, (new ReleaseDomain())->of($select));
        self::assertSame(3, (new ReleaseDomain())->of(new \SqlSemantics\Platform\MySql\Statement\Query\Clause\OutputOrdinal($literal)));
    }

    public function testOfKeepsALimitBeforeUnionInMySql56(): void
    {
        $selection = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection([new \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredProjection(new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new NumberLiteral('1'), new NumberLiteral('2')]))], limit: new \SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit(new NumberLiteral('1')));
        $union = new \SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation(new \SqlSemantics\Platform\MySql\Statement\Query\With\DeferredBody($selection), \SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator::Union, null, new Select([], [new SelectExpression(new NumberLiteral('3'))]));
        self::assertSame(1, (new ReleaseDomain())->of($union));
        self::assertSame(511, (new ReleaseDomain())->of($union->right));
    }

    public function testBitIdentifiesSupportedGrammarReleases(): void
    {
        self::assertSame(1, (new ReleaseDomain())->bit(GrammarRelease::MySql5651));
        self::assertSame(256, (new ReleaseDomain())->bit(GrammarRelease::MySql910));
    }

    public function testOfKeepsTheExplicitLanguageAndLaterTypeDomains(): void
    {
        $rule = new ReleaseDomain();
        self::assertSame(16, $rule->of(new LanguageProfile(GrammarRelease::MySql820)));
        self::assertSame(384, $rule->of(\SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind::Vector));
        self::assertSame(ReleaseDomain::ALL, $rule->of(new NumberLiteral('1')));
    }

    public function testExplanationKeepsTheMeaningOfAnIgnoredOrDeferredTarget(): void
    {
        $query = new QueryRoot(new Select([], [new SelectExpression(new NumberLiteral('1'))]));
        $rule = new ReleaseDomain();
        self::assertSame(48, $rule->explanation(new Explain($query, emptyInto: true)));
        self::assertSame(8, $rule->explanation(new DeferredExplain($query, new Name('JSON'), new UserVariable(new Name('')))));
        self::assertSame(56, $rule->explanation(new DeferredExplain($query, new Name('JSON'), new UserVariable(new Name(str_repeat('a', 65))))));
    }
    public function testOfRetainsLegacyIndexOrderingWithoutAllowingModernFunctionalParts(): void
    {
        $part = new \SqlSemantics\Platform\MySql\Statement\Table\Key\ColumnPart(new Name('a'), direction: \SqlSemantics\Platform\MySql\Statement\Query\Direction::Ascending);
        $index = new \SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition(\SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind::FullText, [$part]);
        self::assertSame(3, (new ReleaseDomain())->of($index));
        $this->expectExceptionMessage('The retained operands must share a supported MySQL release.');
        new \SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition($index->kind, [$part, new \SqlSemantics\Platform\MySql\Statement\Table\Key\ExpressionPart(new NumberLiteral('1'))]);
    }

    public function testModifiersKeepsConflictingCacheModifiersOutOfTheLegacyReleasesThatRefuseThem(): void
    {
        $rule = new ReleaseDomain();

        self::assertSame(508, $rule->modifiers([\SqlSemantics\Platform\MySql\Statement\Query\SelectOption::NoCache, \SqlSemantics\Platform\MySql\Statement\Query\SelectOption::NoCache]));
        self::assertSame(510, $rule->modifiers([\SqlSemantics\Platform\MySql\Statement\Query\SelectOption::NoCache, \SqlSemantics\Platform\MySql\Statement\Query\SelectOption::All, \SqlSemantics\Platform\MySql\Statement\Query\SelectOption::Cache]));
        self::assertSame(511, $rule->modifiers([\SqlSemantics\Platform\MySql\Statement\Query\SelectOption::NoCache]));
        self::assertSame(508, $rule->of(new Select([\SqlSemantics\Platform\MySql\Statement\Query\SelectOption::NoCache, \SqlSemantics\Platform\MySql\Statement\Query\SelectOption::NoCache], [new SelectExpression(new NumberLiteral('1'))])));
    }

    public function testOperandKeepsALaterOperandWithNoCacheInTheModernReleases(): void
    {
        $rule = new ReleaseDomain();
        $noCache = new Select([\SqlSemantics\Platform\MySql\Statement\Query\SelectOption::NoCache], [new SelectExpression(new NumberLiteral('1'))]);

        self::assertSame(508, $rule->operand($noCache));
        self::assertSame(511, $rule->operand(new Select([], [new SelectExpression(new NumberLiteral('1'))])));
        self::assertSame(508, $rule->of(new \SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable($noCache, new Name('d'))));
        self::assertSame(508, $rule->of(new \SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation(new Select([], [new SelectExpression(new NumberLiteral('1'))]), \SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator::Union, null, $noCache)));
    }

    public function testSubqueryKeepsAllWithDistinctInMySql56Only(): void
    {
        $rule = new ReleaseDomain();
        $quantified = new Select([\SqlSemantics\Platform\MySql\Statement\Query\SelectOption::All, \SqlSemantics\Platform\MySql\Statement\Query\SelectOption::Distinct], [new SelectExpression(new NumberLiteral('1'))]);
        $noCache = new Select([\SqlSemantics\Platform\MySql\Statement\Query\SelectOption::NoCache], [new SelectExpression(new NumberLiteral('1'))]);

        self::assertSame(1, $rule->subquery($quantified));
        self::assertSame(508, $rule->subquery($noCache));
        self::assertSame(1, $rule->of(new \SqlSemantics\Platform\MySql\Statement\Expression\Subquery\ScalarSubquery($quantified)));
    }
}
