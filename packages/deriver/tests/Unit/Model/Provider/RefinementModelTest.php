<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Provider;

use Deriver\Model\Provider\RefinementModel;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Model\Provider\RefinementModel
 */
#[CoversClass(RefinementModel::class)]
#[UsesClass(Term::class)]
#[Small]
final class RefinementModelTest extends TestCase
{
    public function testRefineReturnsAGuaranteedImplicationOfTheTrueBranch(): void
    {
        $predicate = Term::parameter('flag', 'bool');
        self::assertSame($predicate, (new \Tests\Fake\PolicyProvider())->refine($predicate, true));
    }
}
