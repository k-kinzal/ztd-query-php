<?php

declare(strict_types=1);

namespace Tests\Unit\Rule;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use ZtdQuery\PhpStanCustomRules\Rule\NoNonOverrideMethodInTestClassRule;

/**
 * @extends RuleTestCase<NoNonOverrideMethodInTestClassRule>
 */
#[CoversClass(NoNonOverrideMethodInTestClassRule::class)]
#[Medium]
final class NoNonOverrideMethodInTestClassRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new NoNonOverrideMethodInTestClassRule();
    }

    public function testDetectsNonOverrideAndMissingOverrideAttribute(): void
    {
        $this->analyse([
            __DIR__ . '/../../Fixture/NonOverrideMethodFixture.php',
        ], [
            ['Override method assertPostConditions() must have the #[\Override] attribute. Test classes should only contain test methods and overrides explicitly marked with #[\Override].', 28],
            ['Method customHelper() is not an override in Tests\Unit\Fixture\NonOverrideMethodFixtureTest. Test classes should only contain test methods and framework overrides. Move helper logic to a dedicated class or inline it into the test method.', 32],
            ['Method nonExistentInParent() is not an override in Tests\Integration\Fixture\IntegrationNonOverrideFixtureTest. Test classes should only contain test methods and framework overrides. Move helper logic to a dedicated class or inline it into the test method.', 61],
        ]);
    }
}
