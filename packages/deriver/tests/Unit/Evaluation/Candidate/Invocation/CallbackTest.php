<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Candidate\Invocation;

use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fake\CandidateFixture as F;

#[CoversNothing]
final class CallbackTest extends TestCase
{
    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testValueUsesInstanceDispatch(): void
    {
        $engine = F::evaluator('class C{function apply($v){return $v+1;}}function target(){$f=[new C,"apply"];return $f(2);}');
        self::assertSame(3, F::value($engine)->native());
    }

}
