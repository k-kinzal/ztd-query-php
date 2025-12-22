<?php

declare(strict_types=1);

namespace ZtdQuery\PhpStanCustomRules\Rule;

use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\TrinaryLogic;
use ZtdQuery\PhpStanCustomRules\Support\TestClassScope;

/**
 * @implements Rule<\PhpParser\Node\Stmt\ClassMethod>
 */
final class NoNonOverrideMethodInTestClassRule implements Rule
{
    public function getNodeType(): string
    {
        return \PhpParser\Node\Stmt\ClassMethod::class;
    }

    /**
     * @param \PhpParser\Node\Stmt\ClassMethod $node
     * @return list<IdentifierRuleError>
     */
    public function processNode(\PhpParser\Node $node, Scope $scope): array
    {
        if (!TestClassScope::isRestrictedTestClass($scope)) {
            return [];
        }

        $methodName = $node->name->toString();

        if ($this->isTestMethod($node)) {
            return [];
        }

        if (str_starts_with($methodName, 'provider')) {
            return [];
        }

        $classReflection = $scope->getClassReflection();
        if ($classReflection === null) {
            return [];
        }

        $parentClass = $classReflection->getParentClass();
        if ($parentClass === null) {
            return $this->buildNonOverrideError($methodName, $classReflection->getName(), $node->getStartLine());
        }

        if (!$parentClass->hasMethod($methodName)) {
            return $this->buildNonOverrideError($methodName, $classReflection->getName(), $node->getStartLine());
        }

        $parentMethod = $parentClass->getMethod($methodName, $scope);
        $isAbstract = $parentMethod->isAbstract();
        if ($isAbstract instanceof TrinaryLogic ? $isAbstract->yes() : $isAbstract === true) {
            return [];
        }

        if ($this->hasOverrideAttribute($node)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                sprintf(
                    'Override method %s() must have the #[\\Override] attribute. Test classes should only contain test methods and overrides explicitly marked with #[\\Override].',
                    $methodName
                )
            )
                ->identifier('customRules.testClassOverrideMustHaveAttribute')
                ->line($node->getStartLine())
                ->build(),
        ];
    }

    private function isTestMethod(\PhpParser\Node\Stmt\ClassMethod $node): bool
    {
        if (str_starts_with($node->name->toString(), 'test')) {
            return true;
        }

        foreach ($node->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                $attrName = $attr->name->toString();
                if ($attrName === 'Test' || str_ends_with($attrName, '\\Test')) {
                    return true;
                }
            }
        }

        return false;
    }

    private function hasOverrideAttribute(\PhpParser\Node\Stmt\ClassMethod $node): bool
    {
        foreach ($node->attrGroups as $attrGroup) {
            foreach ($attrGroup->attrs as $attr) {
                $name = $attr->name->toString();
                if ($name === 'Override' || $name === '\\Override') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    private function buildNonOverrideError(string $methodName, string $className, int $line): array
    {
        return [
            RuleErrorBuilder::message(
                sprintf(
                    'Method %s() is not an override in %s. Test classes should only contain test methods and framework overrides. Move helper logic to a dedicated class or inline it into the test method.',
                    $methodName,
                    $className
                )
            )
                ->identifier('customRules.testClassNonOverrideMethod')
                ->line($line)
                ->build(),
        ];
    }
}
