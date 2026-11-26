<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Expression\Choice;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Expression\Choice\SearchedCaseArm;
use SqlSemantics\Platform\MySql\Ir\Expression\Choice\SignedCase;
use SqlSemantics\Platform\MySql\Ir\Expression\Choice\SimpleCaseArm;
use SqlSemantics\Platform\MySql\Ir\NullLiteral;
use SqlSemantics\Platform\MySql\Ir\Predicate\RangeIntegerLiteral;

#[CoversClass(SignedCase::class)]
#[Small]
final class SignedCaseTest extends TestCase
{
    public function testChildrenPreserveOrderAndDetachArrayReferences(): void
    {
        $match = new RangeIntegerLiteral('2');
        $result = new RangeIntegerLiteral('7');
        $subject = new RangeIntegerLiteral('3');
        $last = new NullLiteral();
        $original = new SimpleCaseArm($match, $result);
        $arm = $original;
        $case = new SignedCase([&$arm, $arm], $subject, $last);
        $arm = new SimpleCaseArm($last, $last);

        self::assertSame([$original, $original], $case->arms);
        self::assertSame([$subject, $match, $result, $match, $result, $last], $case->children());
    }

    public function testRejectsMixedDecisionForms(): void
    {
        $null = new NullLiteral();
        $this->expectExceptionMessage('A signed CASE arm must match the selected decision form.');
        new SignedCase([new SearchedCaseArm($null, $null)], $null);
    }

    public function testRejectsEmptyArms(): void
    {
        $this->expectExceptionMessage('A signed CASE requires one to 32 ordered arms.');
        new SignedCase([]);
    }
}
