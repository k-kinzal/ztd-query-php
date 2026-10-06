<?php

declare(strict_types=1);

namespace Tests\Differential;

use Deriver\Query\Budget;
use Deriver\Query\ReturnQuery;
use JsonException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use Tests\Fake\Programs\DependencyPrograms;
use Tests\Fake\RuntimeOracle;
use Tests\Semantic\CandidateContractTest;

/**
 * Uses PHP 8.3 as an independent oracle across syntax and dependency boundaries.
 */
#[CoversNothing]
#[Medium]
final class DependencyCompositionTest extends TestCase
{
    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    #[DataProviderExternal(DependencyPrograms::class, 'dispatch')]
    #[DataProviderExternal(DependencyPrograms::class, 'recursion')]
    public function testClosedDependencyCompositionsAgreeWithPhp(string $source): void
    {
        $expected = RuntimeOracle::evaluate('<?php '.$source)->native();
        $result = CandidateContractTest::session($source)->derive(new ReturnQuery('target', budget: new Budget(maxDepth: 256)));
        self::assertSame([], CandidateContractTest::frontiers($result));
        self::assertSame([$expected], CandidateContractTest::native($result, 'return'));
    }

    /**
     * @throws JsonException If fixture metadata cannot be encoded
     */
    #[DataProviderExternal(DependencyPrograms::class, 'mutations')]
    public function testStoredValuesSurviveEveryMutationEntry(string $source): void
    {
        $expected = RuntimeOracle::evaluate('<?php '.$source)->native();
        $origin = str_contains($source, 'function change()');
        $result = CandidateContractTest::session($source)->derive(new ReturnQuery($origin ? 'Box::get' : 'target'));
        self::assertSame([], CandidateContractTest::frontiers($result));
        $values = CandidateContractTest::native($result, 'return');
        if ($origin) {
            self::assertContains($expected, $values);
        } else {
            self::assertSame([$expected], $values);
        }
    }
}
