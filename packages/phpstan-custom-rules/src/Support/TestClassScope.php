<?php

declare(strict_types=1);

namespace ZtdQuery\PhpStanCustomRules\Support;

use PHPStan\Analyser\Scope;

final class TestClassScope
{
    public static function isTestClass(Scope $scope): bool
    {
        $classReflection = $scope->getClassReflection();
        if ($classReflection === null) {
            return false;
        }

        $className = ltrim($classReflection->getName(), '\\');
        return str_starts_with($className, 'Tests\\');
    }

    public static function isRestrictedTestClass(Scope $scope): bool
    {
        if (!self::isTestClass($scope)) {
            return false;
        }

        $classReflection = $scope->getClassReflection();
        if ($classReflection === null) {
            return false;
        }

        $className = ltrim($classReflection->getName(), '\\');
        return str_starts_with($className, 'Tests\\Unit\\')
            || str_starts_with($className, 'Tests\\Integration\\');
    }
}
