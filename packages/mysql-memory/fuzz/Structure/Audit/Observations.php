<?php

declare(strict_types=1);

namespace Fuzz\Structure\Audit;

use Fuzz\Target\LibraryErrors;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Statement\Scalar;

/**
 * Compares direct observations under bounded, recorded exception contracts.
 * Original observations are retained separately by the campaign.
 */
final class Observations
{
    /**
     * @param array<string, mixed> $expected The complete native observation
     * @param array<string, mixed> $actual The complete memory observation
     * @return array{matches: bool, contracts: list<string>} Equality and the exceptions validated independently on both sides
     */
    public function compare(Statement|Scalar $node, string $release, array $expected, array $actual): array
    {
        $library = new LibraryErrors();
        $left = $library->comparable($expected, $release);
        $right = $library->comparable($actual, $release);
        $contracts = $left !== $expected && $right !== $actual ? ['missing-library-os-errno-2-or-11'] : [];
        $left = (new EngineReport())->comparable($node, (new CatalogRows())->comparable($node, $left), false);
        $right = (new EngineReport())->comparable($node, (new CatalogRows())->comparable($node, $right), true);
        $leftContracts = $left['contracts'] ?? [];
        $rightContracts = $right['contracts'] ?? [];
        if (is_array($leftContracts) && is_array($rightContracts)) {
            $contracts = [...$contracts, ...array_values(array_intersect(array_filter($leftContracts, is_string(...)), array_filter($rightContracts, is_string(...))))];
        }

        return ['matches' => $left === $right, 'contracts' => $contracts];
    }
}
