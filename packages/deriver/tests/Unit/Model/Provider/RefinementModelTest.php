<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Model\Provider\RefinementModel
 */
#[CoversClass(\Deriver\Model\Provider\RefinementModel::class)]
#[UsesClass(\Deriver\Model\Provider\DomainProvider::class)]
#[UsesClass(\Deriver\Model\Provider\Provider::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class RefinementModelTest extends TestCase
{
    public function testRefineReturnsAGuaranteedImplicationOfTheTrueBranch(): void
    {
        $predicate = \Deriver\Value\Term::parameter('flag', 'bool');
        self::assertSame($predicate, (new \Tests\Fake\PolicyProvider())->refine($predicate, true));
    }
}
