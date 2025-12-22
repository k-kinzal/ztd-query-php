<?php

declare(strict_types=1);

namespace Tests\Unit\Rule;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use ZtdQuery\PhpStanCustomRules\Rule\SrcUnitTestPairRule;

/**
 * @extends RuleTestCase<SrcUnitTestPairRule>
 */
#[CoversClass(SrcUnitTestPairRule::class)]
#[Medium]
final class SrcUnitTestPairRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new SrcUnitTestPairRule(['*Excluded.php']);
    }

    public function testReportsMissingUnitTestForSourceFile(): void
    {
        $this->analyse([
            __DIR__ . '/../../Fixture/PairRulePackage/src/MissingTest.php',
        ], [
            [
                'Source file "src/MissingTest.php" requires a matching unit test file "tests/Unit/MissingTestTest.php" to keep behavior verifiable.',
                1,
            ],
        ]);
    }

    public function testReportsMissingSourceForUnitTestFile(): void
    {
        $this->analyse([
            __DIR__ . '/../../Fixture/PairRulePackage/tests/Unit/MissingSourceTest.php',
        ], [
            [
                'Unit test file "tests/Unit/MissingSourceTest.php" requires a matching source file "src/MissingSource.php" to avoid stale or orphaned tests.',
                1,
            ],
        ]);
    }

    public function testPassesWhenSourceAndUnitTestArePaired(): void
    {
        $this->analyse([
            __DIR__ . '/../../Fixture/PairRulePackage/src/ExistingWithTest.php',
            __DIR__ . '/../../Fixture/PairRulePackage/tests/Unit/ExistingWithTestTest.php',
        ], []);
    }

    public function testSkipsExcludedSourceFiles(): void
    {
        $this->analyse([
            __DIR__ . '/../../Fixture/PairRulePackage/src/SomethingExcluded.php',
        ], []);
    }
}
