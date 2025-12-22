<?php

declare(strict_types=1);

namespace Tests\Unit\Rule;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use ZtdQuery\PhpStanCustomRules\Rule\NoClassConstantInTestClassRule;

/**
 * @extends RuleTestCase<NoClassConstantInTestClassRule>
 */
#[CoversClass(NoClassConstantInTestClassRule::class)]
#[Medium]
final class NoClassConstantInTestClassRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new NoClassConstantInTestClassRule();
    }

    public function testDetectsClassConstantsInRestrictedTestClasses(): void
    {
        $this->analyse([
            __DIR__ . '/../../Fixture/TestClassScopeFixture.php',
        ], [
            ['Class constants are prohibited in Tests\\Unit and Tests\\Integration classes. Shared constants encourage fixture coupling and reduce test independence. Use inline literal values in each test method instead.', 11],
            ['Class constants are prohibited in Tests\\Unit and Tests\\Integration classes. Shared constants encourage fixture coupling and reduce test independence. Use inline literal values in each test method instead.', 28],
        ]);
    }
}
