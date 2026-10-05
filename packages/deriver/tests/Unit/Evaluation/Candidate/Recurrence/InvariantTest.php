<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Candidate\Recurrence;

use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fake\CandidateFixture as F;

#[CoversNothing]
final class InvariantTest extends TestCase
{
    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testCheckKeepsUnmodifiedReceiverDefinitions(): void
    {
        $engine = F::evaluator('function target($n){$x=42;while($n){$n--;}return $x;}');
        self::assertSame(42, F::value($engine)->native());
    }

}
