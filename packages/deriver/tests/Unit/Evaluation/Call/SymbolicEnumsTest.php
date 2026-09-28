<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Call;

use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Small]
final class SymbolicEnumsTest extends TestCase
{
    /**
     * @throws JsonException If fixture values cannot be encoded
     */
    public function testBindEnumeratesUnitEnumNamesWithoutNonCaseConstants(): void
    {
        $result = \Tests\Fake\Analysis::returns('<?php enum Status{case A;case B;const META=1;}function target(Status $s){return $s->name;}');
        self::assertEqualsCanonicalizing(['A','B'], array_map(static fn (\Deriver\Result\Alternative $outcome) => $outcome->values['return']->native(), $result->normalOutcomes));
    }
}
