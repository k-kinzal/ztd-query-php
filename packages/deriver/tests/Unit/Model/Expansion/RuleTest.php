<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Expansion;

use Deriver\Model\Expansion\Rule as Subject;
use Deriver\Value\Term;
use LogicException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class RuleTest extends TestCase
{
    public function testConstantFunctionNormalizesResolvedGlobalNames(): void
    {
        $rule = Subject::constantFunction('one', '1', '\COUNT', Term::constant(1));
        self::assertSame('count', $rule->name);
    }

    public function testConstantExpressionDoesNotDemandOperands(): void
    {
        $rule = Subject::constantExpression('seven', '1', 'binary', '+', Term::constant(7));
        $request = new \Deriver\Model\Expansion\Request('binary', '+', new \Deriver\Reference\SourceRef('s', 'a.php', 0, 3), static fn (): Term => throw new LogicException('Unexpected demand'));
        $result = ($rule->expand)($request);
        self::assertNotNull($result);
        self::assertSame(7, $result->native());
    }

    public function testMatchesUsesSourceRangesWithoutASnapshotCycle(): void
    {
        $rule = new Subject('x', '1', 'binary', '+', static fn (): Term => Term::constant(1), path:'a.php', start:0, end:3);
        $request = new \Deriver\Model\Expansion\Request('binary', '+', new \Deriver\Reference\SourceRef('new-snapshot', 'a.php', 0, 3), static fn (): Term => Term::constant(0));
        self::assertTrue($rule->matches($request));
    }

}
