<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Dml\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Dml\Handler\HandlerOperands;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler as H;
use SqlSemantics\Platform\MySql\Statement\Expression\Branching\CaseBranch;
use SqlSemantics\Platform\MySql\Statement\Expression\Branching\CaseExpression;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\Exists;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\InQuery;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\QuantifiedComparison;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\Quantifier;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\ScalarSubquery;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;

#[CoversClass(HandlerOperands::class)]
#[Small]
final class HandlerOperandsTest extends TestCase
{
    /**
     * @return iterable<string, array{Scalar}>
     */
    public static function providerMoments(): iterable
    {
        foreach (['scalar', 'exists', 'in', 'any'] as $kind) {
            foreach ([false, true] as $nested) {
                $query = new Select([], [new SelectExpression(new NumberLiteral('1'))]);
                $moment = match ($kind) {
                    'scalar' => new ScalarSubquery($query),
                    'exists' => new Exists($query),
                    'in' => new InQuery(new NumberLiteral('1'), $query),
                    'any' => new QuantifiedComparison(new NumberLiteral('1'), ComparisonOperator::Equal, Quantifier::Any, $query),
                };
                yield $kind . ($nested ? ' in unreachable arm' : '') => [$nested ? new CaseExpression(null, [new CaseBranch(new NumberLiteral('0'), $moment)], new NumberLiteral('0')) : $moment];
            }
        }
    }

    #[DataProvider('providerMoments')]
    public function testCheckPreventsPublishingAQueryBearingScan(Scalar $moment): void
    {
        $this->expectExceptionMessage('A HANDLER read cannot contain a subquery.');
        new H\HandlerScan(new H\OpenHandler(new Name('h')), H\ScanDirection::First, $moment);
    }

    #[DataProvider('providerMoments')]
    public function testCheckPreventsPublishingAQueryBearingIndexRead(Scalar $moment): void
    {
        $this->expectExceptionMessage('A HANDLER read cannot contain a subquery.');
        new H\HandlerIndexRead(new H\OpenHandler(new Name('h')), new Name('i'), H\IndexDirection::First, $moment);
    }

    #[DataProvider('providerMoments')]
    public function testCheckPreventsPublishingAQueryBearingKey(Scalar $moment): void
    {
        $this->expectExceptionMessage('A HANDLER read cannot contain a subquery.');
        new H\HandlerIndexSeek(new H\OpenHandler(new Name('h')), new Name('i'), H\KeyComparison::Equal, [$moment]);
    }

    public function testCheckRetainsOrdinaryScalarExpressions(): void
    {
        $moment = new CaseExpression(null, [new CaseBranch(new NumberLiteral('0'), new NumberLiteral('1'))], new NumberLiteral('0'));
        (new HandlerOperands())->check($moment);

        self::assertSame($moment, (new H\HandlerScan(new H\OpenHandler(new Name('h')), H\ScanDirection::First, $moment))->where);
    }

    public function testCheckRejectsStoredCallsEvenInAnUnreachablePredicateBranch(): void
    {
        $call = new \SqlSemantics\Platform\MySql\Statement\Call\FunctionCall(new Name('f'), schema: new Name('d'));
        $predicate = new CaseExpression(null, [new CaseBranch(new NumberLiteral('0'), $call)], new NumberLiteral('1'));
        $this->expectExceptionMessage('A HANDLER read requires a native interpretation of every function call.');
        new H\HandlerIndexRead(new H\OpenHandler(new Name('h')), new Name('i'), H\IndexDirection::First, $predicate);
    }

    public function testReleasesKeepsOnlyTheNativeInterpretationOfALegacyFunction(): void
    {
        $call = new \SqlSemantics\Platform\MySql\Statement\Call\FunctionCall(new Name('X'), [new \SqlSemantics\Platform\MySql\Statement\Call\CallArgument(new NumberLiteral('1'))]);
        self::assertSame(3, (new HandlerOperands())->releases([$call]));
        self::assertSame(511, (new HandlerOperands())->releases([new NumberLiteral('1')]));
    }

    public function testCheckCannotCombineAStoredSignatureWithANativeOnlyPosition(): void
    {
        $call = new \SqlSemantics\Platform\MySql\Statement\Call\FunctionCall(new Name('x'), [new \SqlSemantics\Platform\MySql\Statement\Call\CallArgument(new NumberLiteral('1')), new \SqlSemantics\Platform\MySql\Statement\Call\CallArgument(new NumberLiteral('2'))]);
        $this->expectExceptionMessage('Native signatures and grammar operands must share a supported language interpretation.');
        new H\HandlerIndexSeek(new H\OpenHandler(new Name('h')), new Name('i'), H\KeyComparison::Equal, [$call]);
    }
}
