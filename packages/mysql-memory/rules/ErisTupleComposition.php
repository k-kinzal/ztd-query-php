<?php

declare(strict_types=1);

namespace Rules;

use Eris\Generator;
use Eris\Generators;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

/**
 * Preserves each operand's position and type in an Eris tuple composition.
 * Eris's homogeneous list annotation otherwise loses constructor argument types.
 *
 * @visibility Rules
 */
final class ErisTupleComposition implements DynamicStaticMethodReturnTypeExtension
{
    /**
     * @param class-string $factory The Eris tuple factory or its operand shrink adapter
     */
    public function __construct(private readonly string $factory = Generators::class)
    {
    }

    /**
     * Selects Eris's generator composition factory.
     */
    public function getClass(): string
    {
        return $this->factory;
    }

    /**
     * Refines only tuple generation; other factories retain their declared types.
     */
    public function isStaticMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'tuple';
    }

    /**
     * Maps the known operand generators to their ordered output types.
     * Unknown argument lists retain Eris's general list annotation.
     */
    public function getTypeFromStaticMethodCall(MethodReflection $methodReflection, StaticCall $methodCall, Scope $scope): ?Type
    {
        $arguments = $methodCall->getArgs();
        $values = [];
        foreach ($arguments as $argument) {
            if ($argument->unpack) {
                return null;
            }
            $values[] = $scope->getType($argument->value);
        }
        if (isset($values[0]) && $values[0]->isArray()->yes()) {
            $arrays = $values[0]->getConstantArrays();
            if (count($arrays) !== 1 || $arrays[0]->getOptionalKeys() !== []) {
                return null;
            }
            $values = $arrays[0]->getValueTypes();
        }
        $shape = ConstantArrayTypeBuilder::createEmpty();
        $generator = new ObjectType(Generator::class);
        foreach ($values as $value) {
            $isGenerator = $generator->isSuperTypeOf($value);
            if ($isGenerator->maybe()) {
                return null;
            }
            $shape->setOffsetValueType(null, $isGenerator->yes() ? $value->getTemplateType(Generator::class, 'T') : $value);
        }
        return new GenericObjectType(Generator::class, [$shape->getArray()]);
    }
}
