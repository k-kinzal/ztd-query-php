<?php

declare(strict_types=1);

namespace Tests\Unit\Analysis\Selection;

use Deriver\Analysis\Selection\ExpressionResolution as Subject;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fake\CandidateApi as A;

#[CoversNothing]
final class ExpressionResolutionTest extends TestCase
{
    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testExpressionRejectsAnUncapturedRange(): void
    {
        $session = A::session();
        $this->expectException(\Deriver\Exception\InvalidInputException::class);
        (new Subject())->expression($session->program, 'candidate.php', 1000, 1001, 'value');
    }

}
