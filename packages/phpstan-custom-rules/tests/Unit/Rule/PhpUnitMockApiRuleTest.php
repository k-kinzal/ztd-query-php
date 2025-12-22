<?php

declare(strict_types=1);

namespace Tests\Unit\Rule;

use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use ZtdQuery\PhpStanCustomRules\Rule\PhpUnitMockApiRule;

/**
 * @extends RuleTestCase<PhpUnitMockApiRule>
 */
#[CoversClass(PhpUnitMockApiRule::class)]
#[Medium]
final class PhpUnitMockApiRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        $reflectionProvider = self::getContainer()->getByType(ReflectionProvider::class);

        return new PhpUnitMockApiRule($reflectionProvider);
    }

    public function testDetectsProhibitedPhpUnitMockApisAndNonInterfaceTargets(): void
    {
        $this->analyse([
            __DIR__ . '/../../Fixture/PhpUnitMockApiFixture.php',
        ], [
            ['PHPUnit createMock() must target an interface; "stdClass" is not an interface. Mock only interfaces to keep tests decoupled from implementations.', 25],
            ['PHPUnit createConfiguredMock() must target an interface; "stdClass" is not an interface. Mock only interfaces to keep tests decoupled from implementations.', 26],
            ['PHPUnit createStub() must target an interface; "stdClass" is not an interface. Mock only interfaces to keep tests decoupled from implementations.', 27],
            ['PHPUnit createConfiguredStub() must target an interface; "stdClass" is not an interface. Mock only interfaces to keep tests decoupled from implementations.', 28],
            ['PHPUnit createMock() must use a direct interface class-string literal (e.g. DependencyInterface::class). Variables and string literals are not allowed because the type must be statically verifiable.', 34],
            ['PHPUnit createStub() must use a direct interface class-string literal (e.g. DependencyInterface::class). Variables and string literals are not allowed because the type must be statically verifiable.', 35],
            ['PHPUnit getMockBuilder() is prohibited. Use createMock(FooInterface::class) or createStub(FooInterface::class) instead. These APIs enforce interface-based test doubles for better decoupling.', 40],
            ['PHPUnit createPartialMock() is prohibited. Use createMock(FooInterface::class) or createStub(FooInterface::class) instead. These APIs enforce interface-based test doubles for better decoupling.', 41],
            ['PHPUnit createTestProxy() is prohibited. Use createMock(FooInterface::class) or createStub(FooInterface::class) instead. These APIs enforce interface-based test doubles for better decoupling.', 42],
            ['PHPUnit getMockForAbstractClass() is prohibited. Use createMock(FooInterface::class) or createStub(FooInterface::class) instead. These APIs enforce interface-based test doubles for better decoupling.', 43],
            ['PHPUnit getMockForTrait() is prohibited. Use createMock(FooInterface::class) or createStub(FooInterface::class) instead. These APIs enforce interface-based test doubles for better decoupling.', 44],
            ['PHPUnit getMockFromWsdl() is prohibited. Use createMock(FooInterface::class) or createStub(FooInterface::class) instead. These APIs enforce interface-based test doubles for better decoupling.', 45],
            ['Direct instantiation of PHPUnit\\Framework\\MockObject\\MockBuilder is prohibited. Use createMock(FooInterface::class) or createStub(FooInterface::class) instead.', 50],
            ['Direct instantiation of PHPUnit\\Framework\\MockObject\\Generator\\Generator is prohibited. Use createMock(FooInterface::class) or createStub(FooInterface::class) instead.', 51],
            ['PHPUnit createMock() must target an interface; "stdClass" is not an interface. Mock only interfaces to keep tests decoupled from implementations.', 56],
            ['PHPUnit createStub() must target an interface; "stdClass" is not an interface. Mock only interfaces to keep tests decoupled from implementations.', 57],
            ['PHPUnit createMock() must use a direct interface class-string literal (e.g. DependencyInterface::class). Variables and string literals are not allowed because the type must be statically verifiable.', 68],
            ['PHPUnit createStub() must use a direct interface class-string literal (e.g. DependencyInterface::class). Variables and string literals are not allowed because the type must be statically verifiable.', 69],
            ['PHPUnit createMock() must target an interface; "stdClass" is not an interface. Mock only interfaces to keep tests decoupled from implementations.', 74],
        ]);
    }
}
