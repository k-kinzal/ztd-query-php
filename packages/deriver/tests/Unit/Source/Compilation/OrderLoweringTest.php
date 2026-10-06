<?php

declare(strict_types=1);

namespace Tests\Unit\Source\Compilation;

use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Small]
final class OrderLoweringTest extends TestCase
{
    /**
     * @throws JsonException If fixture values cannot be encoded
     */
    public function testBinaryExploresBothOrdersWithoutDuplicatingCallSites(): void
    {
        $session = \Tests\Fake\Analysis::session('<?php function sink($x){} function target(){$n=1;sink($n+++$n);}');
        self::assertCount(1, $session->callsTo('sink'));
        $result = $session->derive(new \Deriver\Query\ValueQuery($session->callsTo('sink')[0]->argument(0)));
        self::assertEqualsCanonicalizing([2, 3], array_map(static fn (\Deriver\Result\Alternative $outcome) => $outcome->values['value']->native(), $result->normalOutcomes));
    }
}
