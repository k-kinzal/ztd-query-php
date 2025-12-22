<?php

declare(strict_types=1);

namespace Tests\Unit\Rule;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use ZtdQuery\PhpStanCustomRules\Rule\ForbiddenMagicMethodCallRule;

/**
 * @extends RuleTestCase<ForbiddenMagicMethodCallRule>
 */
#[CoversClass(ForbiddenMagicMethodCallRule::class)]
#[Medium]
final class ForbiddenMagicMethodCallRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new ForbiddenMagicMethodCallRule();
    }

    public function testDetectsForbiddenMagicMethodCalls(): void
    {
        $this->analyse([
            __DIR__ . '/../../Fixture/ForbiddenMagicMethodCallFixture.php',
        ], [
            ['Direct call to magic method __toString() is prohibited. Magic methods are invoked implicitly by PHP; calling them directly bypasses language semantics. Use the corresponding language construct instead (e.g. (string)$obj instead of $obj->__toString()).', 21],
            ['Direct call to magic method __clone() is prohibited. Magic methods are invoked implicitly by PHP; calling them directly bypasses language semantics. Use the corresponding language construct instead (e.g. (string)$obj instead of $obj->__toString()).', 22],
            ['Direct call to magic method __toString() is prohibited. Magic methods are invoked implicitly by PHP; calling them directly bypasses language semantics. Use the corresponding language construct instead (e.g. (string)$obj instead of $obj->__toString()).', 47],
            ['Direct call to magic method __callStatic() is prohibited. Magic methods are invoked implicitly by PHP; calling them directly bypasses language semantics. Use the corresponding language construct instead (e.g. (string)$obj instead of $obj->__toString()).', 60],
        ]);
    }
}
