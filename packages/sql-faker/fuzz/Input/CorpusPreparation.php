<?php

declare(strict_types=1);

namespace SqlFaker\Fuzz\Input;

use LogicException;
use SqlFaker\Fuzz\Run\FuzzSetup;
use SqlFaker\Grammar\GenerationException;
use SqlFaker\Grammar\LexicalException;

/**
 * Builds immutable grammar witnesses, separately from PHP-Fuzzer's evolving corpus.
 */
final class CorpusPreparation
{
    /**
     * Generates seeds without using historical coverage to schedule exploration.
     *
     * @return array<string, array{status: string, minimumCost: int|null, file: string|null}>
     * @throws LogicException When a seed does not reproduce its planned production
     */
    public function prepare(FuzzSetup $setup, string $directory): array
    {
        $inventory = $setup->coverage->inventory();
        $search = new ProductionWitness($inventory->grammar, $setup->lexical->supports(...));
        $encoder = new WitnessEncoder($inventory->grammar, $setup->costs, $setup->decoder);
        $report = [];
        foreach ($inventory->grammar->ruleMap as $name => $rule) {
            foreach ($rule->alternatives as $ordinal => $production) {
                $id = $inventory->id($name, $production, $ordinal);
                if (!$inventory->entries[$id]['rootReachable']) {
                    $report[$id] = ['status' => 'outside-root', 'minimumCost' => null, 'file' => null];
                    continue;
                }
                $witness = $search->find($inventory->root, $name, $ordinal);
                if ($witness === null || $witness->cost > $setup->decoder->maximum) {
                    $report[$id] = ['status' => $witness === null ? 'no-lexically-supported-witness' : 'exceeds-budget',
                        'minimumCost' => $witness?->cost, 'file' => null];
                    continue;
                }
                $input = $encoder->encode($witness);
                $file = hash('sha256', $input) . '.bin';
                file_put_contents($directory . '/' . $file, $input);
                $status = $this->verify($setup, $input, $id);
                $report[$id] = ['status' => $status, 'minimumCost' => $witness->cost, 'file' => $file];
                copy($directory . '/' . $file, $setup->corpus . '/' . $file);
            }
        }
        $setup->coverage->reset();
        return $report;
    }

    /**
     * Verifies real generation reaches the desired alternative without DB or SQL rewriting.
     *
     * @throws LogicException When encoding disagrees with actual production selection
     */
    public function verify(FuzzSetup $setup, string $input, string $id): string
    {
        try {
            $setup->provider->generate($setup->decoder->decode($input));
        } catch (GenerationException|LexicalException $failure) {
            return 'generation-failure: ' . $failure->getMessage();
        }
        if (!in_array($id, $setup->coverage->lastGeneration()['reachedIds'] ?? [], true)) {
            throw new LogicException('Encoded seed did not reach its intended production: ' . $id);
        }
        return 'witness-verified';
    }
}
