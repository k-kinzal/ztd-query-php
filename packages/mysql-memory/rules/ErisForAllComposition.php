<?php

declare(strict_types=1);

namespace Rules;

use Eris\Generator;
use Eris\Quantifier\ForAll;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPUnit\Framework\TestCase;

/**
 * Carries the first generated value's type into Eris's execution callback.
 * Eris uses func_get_args(), so its native method has no parameter to annotate.
 *
 * @visibility Rules
 */
final class ErisForAllComposition implements DynamicMethodReturnTypeExtension
{
    /**
     * Selects test classes using the native Eris trait.
     */
    public function getClass(): string
    {
        return TestCase::class;
    }

    /**
     * Refines only the native property quantifier.
     */
    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'forAll';
    }

    /**
     * Preserves the first callback argument; subsequent values keep their native unknown type.
     */
    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): ?Type
    {
        $arguments = $methodCall->getArgs();
        if ($arguments === [] || $arguments[0]->unpack) {
            return null;
        }
        $generator = $scope->getType($arguments[0]->value);
        if (!(new ObjectType(Generator::class))->isSuperTypeOf($generator)->yes()) {
            return null;
        }
        return new GenericObjectType(ForAll::class, [$generator->getTemplateType(Generator::class, 'T')]);
    }
}
