<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Candidate\Language;

use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fake\CandidateFixture as F;

#[CoversNothing]
final class ConstantsTest extends TestCase
{
    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testValueUsesTheTargetIntegerProfile(): void
    {
        $engine = F::evaluator('function target(){return PHP_INT_SIZE;}');
        self::assertSame(8, F::value($engine)->native());
    }

}
