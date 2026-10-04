<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Candidate\Language;

use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fake\CandidateFixture as F;

#[CoversNothing]
final class DeclarationCoercionTest extends TestCase
{
    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testCheckUsesDeclarationCoercions(): void
    {
        $e = F::evaluator('function number(int $x){return $x;}function target(){return number("7");}');
        self::assertSame(7, F::value($e)->native());
    }
}
