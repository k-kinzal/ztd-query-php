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
    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testCoerceUsesDeclaredSemantics(): void
    {
        self::assertSame(2.0, \Tests\Fake\CandidateApi::returns('function target():float{return 2;}')->candidates[0]->result);
    }

    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testAcceptUsesDeclaredSemantics(): void
    {
        self::assertSame([1], \Tests\Fake\CandidateApi::returns('function target():iterable{return [1];}')->candidates[0]->result);
    }

    /**
     * @throws JsonException If captured fixture metadata cannot be encoded
     */
    public function testCallableUsesDeclaredSemantics(): void
    {
        self::assertSame(7, \Tests\Fake\CandidateApi::returns('function f(){return 7;}function accept(callable $f){return $f();}function target(){return accept("f");}')->candidates[0]->result);
    }

}
