<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Candidate\Memory;

use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fake\CandidateFixture as F;

#[CoversNothing]
final class PropertiesTest extends TestCase
{
    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testAllocatedBindsConstructorInputsPerAllocation(): void
    {
        $e = F::evaluator('class B{function __construct(public int $x){}}function target(){$a=new B(3);return $a->x;}');
        self::assertSame(3, F::value($e)->native());
        self::assertSame(['B::__construct' => 1], $e->context->bodies);
    }
}
