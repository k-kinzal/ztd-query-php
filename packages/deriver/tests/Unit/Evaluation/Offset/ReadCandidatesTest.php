<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Offset;

use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Small]
final class ReadCandidatesTest extends TestCase
{
    /**
     * @throws JsonException If fixture values cannot be encoded
     */
    public function testApplyKeepsRepeatedSelectionsCorrelated(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function target(string $key){$a=["a"=>1,"b"=>2];return [$a[$key],$a[$key]];}');
        $values = array_map(static fn (\Deriver\Result\Alternative $outcome) => $outcome->values['return']->native(), $result->normalOutcomes);
        self::assertEqualsCanonicalizing([[1,1],[2,2],[null,null]], $values);
    }
    /**
     * @throws JsonException If fixture inputs cannot be encoded
     */
    public function testApplyKeepsInvalidKeyExceptionForSymbolicArrays(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php function target(array $a,$key){return $a[$key];}');
        self::assertNotEmpty($result->normalOutcomes);
        self::assertContains('TypeError', array_map(static fn ($outcome) => $outcome->exception->literal, $result->exceptionalOutcomes));
    }

}
