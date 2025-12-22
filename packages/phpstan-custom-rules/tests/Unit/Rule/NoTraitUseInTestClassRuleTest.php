<?php

declare(strict_types=1);

namespace Tests\Unit\Rule;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use ZtdQuery\PhpStanCustomRules\Rule\NoTraitUseInTestClassRule;

/**
 * @extends RuleTestCase<NoTraitUseInTestClassRule>
 */
#[CoversClass(NoTraitUseInTestClassRule::class)]
#[Medium]
final class NoTraitUseInTestClassRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new NoTraitUseInTestClassRule();
    }

    public function testDetectsTraitUseInRestrictedTestClasses(): void
    {
        $this->analyse([
            __DIR__ . '/../../Fixture/TraitUseInTestClassFixture.php',
        ], [
            ['Trait usage is prohibited in Tests\\Unit and Tests\\Integration classes. Traits can circumvent test class restrictions (no properties, no constants, no private methods). Move shared behavior to a dedicated helper class and call it explicitly.', 21],
            ['Trait usage is prohibited in Tests\\Unit and Tests\\Integration classes. Traits can circumvent test class restrictions (no properties, no constants, no private methods). Move shared behavior to a dedicated helper class and call it explicitly.', 36],
        ]);
    }
}
