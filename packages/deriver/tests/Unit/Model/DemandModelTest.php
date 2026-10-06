<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Semantic\CandidateContractTest as C;

#[CoversNothing]
final class DemandModelTest extends TestCase
{
    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testDemandEvaluatesOnlyTheSelectionParameter(): void
    {
        $s = C::session('function target(){observe(heavy("fast",missing()));}', new \Deriver\Project\Configuration(models:[new \Tests\Fake\SelectiveModel()]));
        $r = C::argument($s);
        self::assertSame([10], C::native($r));
        self::assertSame(0, $r->statistics->bodyExpansions);
    }
}
