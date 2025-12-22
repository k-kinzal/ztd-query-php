<?php

declare(strict_types=1);

namespace Tests\Unit\Rule;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use ZtdQuery\PhpStanCustomRules\Rule\NoPropertyInTestClassRule;

/**
 * @extends RuleTestCase<NoPropertyInTestClassRule>
 */
#[CoversClass(NoPropertyInTestClassRule::class)]
#[Medium]
final class NoPropertyInTestClassRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new NoPropertyInTestClassRule();
    }

    public function testDetectsPropertiesInRestrictedTestClasses(): void
    {
        $this->analyse([
            __DIR__ . '/../../Fixture/TestClassScopeFixture.php',
        ], [
            ['Properties are prohibited in Tests\\Unit and Tests\\Integration classes. Shared state across test methods reduces test isolation and makes failures harder to debug. Declare values as local variables inside each test method instead.', 9],
            ['Properties are prohibited in Tests\\Unit and Tests\\Integration classes. Shared state across test methods reduces test isolation and makes failures harder to debug. Declare values as local variables inside each test method instead.', 26],
        ]);
    }
}
