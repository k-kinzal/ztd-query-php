<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Candidate;

use Deriver\Value\Term;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fake\CandidateFixture;
use Tests\Fake\CandidateFixture as F;

#[CoversNothing]
final class ArrayConstructionTest extends TestCase
{
    /**
     * @throws JsonException If fixture metadata cannot be encoded
 */
    public function testValueDoesNotLoseAnUnknownMiddleEntry(): void
    {
        $e = CandidateFixture::evaluator('function target($x){return ["head",$x,"tail"];}');
        $f = CandidateFixture::frame($e);
        $v = $e->returns($f, 20);
        self::assertSame('head', $v->operands[0]->literal);
        self::assertSame('reference', $v->operands[1]->kind);
        self::assertSame('tail', $v->operands[2]->literal);
    }
    /**
     * @throws JsonException If captured metadata cannot be encoded
     */
    public function testAppendPreservesTheOrderAfterAnUnknownKey(): void
    {
        $e = F::evaluator();
        $entries = [];
        $next = 0;
        $integer = false;
        $residual = null;
        $builder = new \Deriver\Evaluation\Candidate\ArrayConstruction($e);
        $builder->append($entries, $next, $integer, $residual, Term::parameter('key'), Term::constant('value'));
        self::assertNotNull($residual);
        self::assertSame('array-set', $residual->kind);
        self::assertSame('key', $residual->operands[1]->literal);
    }
}
