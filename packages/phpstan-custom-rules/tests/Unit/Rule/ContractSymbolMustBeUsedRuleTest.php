<?php

declare(strict_types=1);

namespace Tests\Unit\Rule;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use ZtdQuery\PhpStanCustomRules\Rule\ContractSymbolMustBeUsedRule;

/**
 * @extends RuleTestCase<ContractSymbolMustBeUsedRule>
 */
#[CoversClass(ContractSymbolMustBeUsedRule::class)]
#[Medium]
final class ContractSymbolMustBeUsedRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new ContractSymbolMustBeUsedRule();
    }

    public function testPassesWhenContractInterfaceIsImportedByNonContractSource(): void
    {
        $this->analyse([
            __DIR__ . '/../../Fixture/ContractUsagePackage/src/Contract/UsedRuntime.php',
        ], []);
    }

    public function testPassesWhenContractClassIsImportedByNonContractSource(): void
    {
        $this->analyse([
            __DIR__ . '/../../Fixture/ContractUsagePackage/src/Contract/UsedGrammar.php',
        ], []);
    }

    public function testReportsUnusedContractInterface(): void
    {
        $this->analyse([
            __DIR__ . '/../../Fixture/ContractUsagePackage/src/Contract/UnusedRuntime.php',
        ], [
            [
                'Contract symbol "SqlFaker\Contract\UnusedRuntime" must be imported by at least one non-contract SqlFaker source file using a use statement.',
                7,
            ],
        ]);
    }

    public function testReportsUnusedContractClass(): void
    {
        $this->analyse([
            __DIR__ . '/../../Fixture/ContractUsagePackage/src/Contract/UnusedGrammar.php',
        ], [
            [
                'Contract symbol "SqlFaker\Contract\UnusedGrammar" must be imported by at least one non-contract SqlFaker source file using a use statement.',
                7,
            ],
        ]);
    }

    public function testReportsOnlyUnusedContractFunctions(): void
    {
        $this->analyse([
            __DIR__ . '/../../Fixture/ContractUsagePackage/src/Contract/functions.php',
        ], [
            [
                'Contract symbol "SqlFaker\Contract\unused_helper" must be imported by at least one non-contract SqlFaker source file using a use statement.',
                11,
            ],
        ]);
    }
}
