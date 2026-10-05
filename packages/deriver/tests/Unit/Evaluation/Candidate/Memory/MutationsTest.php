<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Candidate\Memory;

use Deriver\Evaluation\Candidate\Memory\Mutations;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fake\CandidateFixture as F;

#[CoversNothing]
final class MutationsTest extends TestCase
{
    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testWritesIncludesEveryDirectStorageMutation(): void
    {
        $frame = F::frame(F::evaluator('function target(){$x=1;$x+=2;$x++;unset($x);}'));
        self::assertTrue(Mutations::writes(F::instruction($frame, 'write')));
        self::assertTrue(Mutations::writes(F::instruction($frame, 'compound')));
        self::assertTrue(Mutations::writes(F::instruction($frame, 'increment')));
        self::assertTrue(Mutations::writes(F::instruction($frame, 'unset')));
        self::assertFalse(Mutations::writes(F::instruction($frame, 'local')));
    }

    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testRootFindsThePropertyBehindAnElementMutation(): void
    {
        $frame = F::frame(F::evaluator('class Box{public $a=[1];function target(){$this->a[0]+=2;}}'), 'Box::target');
        self::assertSame('field-address', Mutations::root($frame->graph, F::instruction($frame, 'compound'))?->operation);
    }

    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testValueDistinguishesPostfixExpressionAndStoredValue(): void
    {
        $engine = F::evaluator('function target(){$x=4;$old=$x++;return [$old,$x];}');
        self::assertSame([4, 5], F::value($engine)->native());
    }
    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testIncrementSharesPhpStringCarrySemantics(): void
    {
        $engine = F::evaluator('function target(){$x="a9";$old=$x++;return [$old,$x];}');
        self::assertSame(['a9', 'b0'], F::value($engine)->native());
    }

    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testIncrementKeepsDiagnosticResultsAndTheirSource(): void
    {
        $engine = F::evaluator('function target(){$x=true;return ++$x;}');
        $value = F::value($engine);
        self::assertTrue($value->native());
        self::assertSame('PHP_WARNING', $value->attributes['reason']);
        self::assertSame('candidate.php', $value->attributes['source']);
    }

}
