<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Candidate\Recurrence;

use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fake\CandidateFixture as F;

#[CoversNothing]
final class IterationTest extends TestCase
{
    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public function testValueUsesFiniteKeysAndValues(): void
    {
        $engine = F::evaluator('function target(){$s="";foreach(["a"=>"x","b"=>"y"] as $k=>$v){$s.=$k.$v;}return $s;}');
        self::assertSame('axby', F::value($engine)->native());
    }

}
