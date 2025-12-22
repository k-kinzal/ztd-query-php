<?php

declare(strict_types=1);

namespace Tests\Unit\Rule;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use ZtdQuery\PhpStanCustomRules\Rule\NoPrivateMethodInTestClassRule;

/**
 * @extends RuleTestCase<NoPrivateMethodInTestClassRule>
 */
#[CoversClass(NoPrivateMethodInTestClassRule::class)]
#[Medium]
final class NoPrivateMethodInTestClassRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new NoPrivateMethodInTestClassRule();
    }

    public function testDetectsPrivateMethodsInRestrictedTestClasses(): void
    {
        $this->analyse([
            __DIR__ . '/../../Fixture/TestClassScopeFixture.php',
        ], [
            ['Private methods are prohibited in Tests\\Unit and Tests\\Integration classes. Over-abstracted helpers hide test intent and make failures harder to understand. Inline the logic into each test method, or extract to a dedicated helper class if reuse is truly needed.', 13],
            ['Private methods are prohibited in Tests\\Unit and Tests\\Integration classes. Over-abstracted helpers hide test intent and make failures harder to understand. Inline the logic into each test method, or extract to a dedicated helper class if reuse is truly needed.', 30],
        ]);
    }
}
