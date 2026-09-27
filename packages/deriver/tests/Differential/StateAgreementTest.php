<?php

declare(strict_types=1);

namespace Tests\Differential;

use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use Tests\Fake\Oracle\RuntimeState;
use Tests\Fake\Oracle\StateAgreement;
use Tests\Fake\Oracle\StatePrograms;

/**
 * Compares generated programs' complete observed heap and completion against PHP 8.3.
 */
#[CoversNothing]
#[Medium]
final class StateAgreementTest extends TestCase
{
    /**
     * @param string $source Trusted generated program
     * @param int $input Exhausted concrete input
     * @throws JsonException If fixture observations cannot be encoded
     */
    #[DataProviderExternal(StatePrograms::class, 'cases')]
    public function testRuntimeStateAndCompletionAreIncluded(string $source, int $input): void
    {
        self::assertContains(RuntimeState::observe($source, $input), StateAgreement::derived($source, $input), $source);
    }
}
