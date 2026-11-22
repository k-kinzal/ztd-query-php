<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Predicate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CaseBranch;
use SqlSemantics\Platform\Sqlite\Ir\Expression\CaseExpression;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Parameter;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\ExpressionScope;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\FoldedMembership;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\MembershipItem;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\MembershipTest;

#[CoversClass(ExpressionScope::class)]
#[Small]
final class ExpressionScopeTest extends TestCase
{
    public function testCheckAcceptsAColumnInTheVisiblePrefix(): void
    {
        $column = new ColumnReference(0, 1);

        (new ExpressionScope())->check([1, 1], [[$column, 0, 2]]);

        self::assertSame(1, $column->source);
    }

    public function testChoiceListsDormantAndActiveOperandsInWrittenOrder(): void
    {
        $one = new IntegerLiteral('1');
        $column = new ColumnReference(0, 1);
        $value = new CaseExpression($column, [new CaseBranch($one, $column)], $one);

        self::assertSame([[$column, 2, 3], [$one, 2, 3], [$column, 2, 3], [$one, 2, 3]], (new ExpressionScope())->choice($value, 2, 3));
    }

    public function testMembersExposeTheTestedValueAndEveryCandidate(): void
    {
        $operand = new IntegerLiteral('1');
        $first = new ColumnReference(3);
        $second = new ColumnReference(8);
        $test = new MembershipTest($operand, [new MembershipItem($first), new MembershipItem($second)]);

        self::assertSame([[$operand, 2, 4], [$first, 2, 4], [$second, 2, 4]], (new ExpressionScope())->members($test, 2, 4));
    }

    public function testBranchesReachEachFoldedBindingRegistration(): void
    {
        $a = new Parameter('02');
        $b = new Parameter('2');
        $value = new FoldedMembership(true, [$a, $b]);

        self::assertSame([[$a, 2, 4], [$b, 2, 4]], (new ExpressionScope())->branches($value, 2, 4));
    }
}
