<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Expression\Choice;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Expression\Choice\SearchedCaseArm;
use SqlSemantics\Platform\MySql\Ir\NullLiteral;
use SqlSemantics\Platform\MySql\Ir\Predicate\RangeIntegerLiteral;

#[CoversClass(SearchedCaseArm::class)]
#[Small]
final class SearchedCaseArmTest extends TestCase
{
    public function testRetainsTheDecisionAndResultRoles(): void
    {
        $result = new RangeIntegerLiteral('2');
        $candidate = new NullLiteral();
        $arm = new SearchedCaseArm($candidate, $result);

        self::assertSame($candidate, $arm->decision);
        self::assertSame($result, $arm->result);
    }
}
