<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Candidate\Enumeration;

use Deriver\Evaluation\Candidate\Enumeration\Suspension as Subject;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fake\CandidateFixture as F;

#[CoversNothing]
final class SuspensionTest extends TestCase
{
    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testExpressionRetainsLiteralAndReferenceOnStop(): void
    {
        $engine = F::evaluator();
        $frame = F::frame($engine);
        $instruction = F::instruction($frame, 'binary');
        $value = (new Subject())->expression($engine->context, $frame, $instruction, 'TIME_LIMIT');
        self::assertSame('TIME_LIMIT', $value->attributes['reason']);
        self::assertSame(2, $value->operands[1]->native());
    }

}
