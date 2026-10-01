<?php

declare(strict_types=1);

namespace Tests\Integration;

use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Tests\Fake\Oracle\StateAgreement;
use Tests\Fake\Oracle\StatePrograms;

#[CoversNothing]
#[Small]
final class GeneratorContractTest extends TestCase
{
    /**
     * @throws JsonException If the default oracle predicate needs to encode a fixture
     */
    public function testShrinkingPreservesTheCounterexampleAndRemovesIndependentStatements(): void
    {
        $operations = ['$a->value += 3;', '$ref += 2;', '$c->child = null;'];
        $minimal = StateAgreement::shrink($operations, 0, static fn (array $candidate): bool => in_array('$ref += 2;', $candidate, true));
        self::assertSame(['$ref += 2;'], $minimal);
        self::assertStringContainsString('$ref = 1;', StatePrograms::source($minimal));
        self::assertStringContainsString('return $ref;', StatePrograms::source($minimal));
    }
}
