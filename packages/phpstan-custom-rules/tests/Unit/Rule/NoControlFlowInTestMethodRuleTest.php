<?php

declare(strict_types=1);

namespace Tests\Unit\Rule;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use ZtdQuery\PhpStanCustomRules\Rule\NoControlFlowInTestMethodRule;

/**
 * @extends RuleTestCase<NoControlFlowInTestMethodRule>
 */
#[CoversClass(NoControlFlowInTestMethodRule::class)]
#[Medium]
final class NoControlFlowInTestMethodRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new NoControlFlowInTestMethodRule();
    }

    public function testDetectsControlFlowInTestMethods(): void
    {
        $this->analyse([
            __DIR__ . '/../../Fixture/ControlFlowInTestMethodFixture.php',
        ], [
            ['Control flow statement "if" is prohibited in test method testWithIf(). Complex control flow in tests indicates the test is doing too much. Split into separate test methods or use data providers for parameterized cases. try-catch is allowed when testing exception behavior.', 14],
            ['Control flow statement "for" is prohibited in test method testWithFor(). Complex control flow in tests indicates the test is doing too much. Split into separate test methods or use data providers for parameterized cases. try-catch is allowed when testing exception behavior.', 21],
            ['Control flow statement "foreach" is prohibited in test method testWithForeach(). Complex control flow in tests indicates the test is doing too much. Split into separate test methods or use data providers for parameterized cases. try-catch is allowed when testing exception behavior.', 28],
            ['Control flow statement "while" is prohibited in test method testWithWhile(). Complex control flow in tests indicates the test is doing too much. Split into separate test methods or use data providers for parameterized cases. try-catch is allowed when testing exception behavior.', 35],
            ['Control flow statement "do-while" is prohibited in test method testWithDoWhile(). Complex control flow in tests indicates the test is doing too much. Split into separate test methods or use data providers for parameterized cases. try-catch is allowed when testing exception behavior.', 42],
            ['Control flow statement "switch" is prohibited in test method testWithSwitch(). Complex control flow in tests indicates the test is doing too much. Split into separate test methods or use data providers for parameterized cases. try-catch is allowed when testing exception behavior.', 49],
            ['Control flow statement "match" is prohibited in test method testWithMatch(). Complex control flow in tests indicates the test is doing too much. Split into separate test methods or use data providers for parameterized cases. try-catch is allowed when testing exception behavior.', 58],
            ['Control flow statement "if" is prohibited in test method attributeTestWithIf(). Complex control flow in tests indicates the test is doing too much. Split into separate test methods or use data providers for parameterized cases. try-catch is allowed when testing exception behavior.', 100],
            ['Control flow statement "if" is prohibited in test method testIntegrationWithIf(). Complex control flow in tests indicates the test is doing too much. Split into separate test methods or use data providers for parameterized cases. try-catch is allowed when testing exception behavior.', 122],
        ]);
    }
}
