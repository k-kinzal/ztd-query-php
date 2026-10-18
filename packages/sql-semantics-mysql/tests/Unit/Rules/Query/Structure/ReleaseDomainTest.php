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

}
