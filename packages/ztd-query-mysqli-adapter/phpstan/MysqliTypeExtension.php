<?php

declare(strict_types=1);

namespace Quality\Mysqli;

use mysqli;
use mysqli_result;
use mysqli_sql_exception;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\ArrayType;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\DynamicMethodThrowTypeExtension;
use PHPStan\Type\FloatType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\NullType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\UnionType;

/**
 * Refines native mysqli result shapes and report-mode exceptions absent from PHPStan's function map.
 */
final class MysqliTypeExtension implements DynamicMethodReturnTypeExtension, DynamicMethodThrowTypeExtension
{
    /**
     * Register independently for the native connection or native result boundary.
     *
     * @param class-string $className
     */
    public function __construct(private string $className)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getClass(): string
    {
        return $this->className;
    }

    /**
     * {@inheritDoc}
     */
    public function isMethodSupported(MethodReflection $methodReflection): bool
    {
        return ($this->className === mysqli_result::class && $methodReflection->getName() === 'fetch_all')
            || ($this->className === mysqli::class && in_array($methodReflection->getName(), ['get_connection_stats', 'query', 'prepare'], true));
    }

    /**
     * {@inheritDoc}
     */
    public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): ?Type
    {
        if ($methodReflection->getName() === 'get_connection_stats') {
            return new ArrayType(new StringType(), new UnionType([new IntegerType(), new StringType()]));
        }
        if ($methodReflection->getName() !== 'fetch_all') {
            return null;
        }
        $mode = $methodCall->getArgs()[0] ?? null;
        $associative = $mode !== null && $scope->getType($mode->value)->equals(new ConstantIntegerType(MYSQLI_ASSOC));
        return new ArrayType(new IntegerType(), new ArrayType(
            $associative ? new StringType() : new UnionType([new IntegerType(), new StringType()]),
            new UnionType([new IntegerType(), new FloatType(), new StringType(), new NullType()]),
        ));
    }

    /**
     * {@inheritDoc}
     */
    public function getThrowTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): ?Type
    {
        if ($this->className === mysqli::class && in_array($methodReflection->getName(), ['query', 'prepare'], true)) {
            $declared = $methodReflection->getThrowType();
            $native = new ObjectType(mysqli_sql_exception::class);
            return $declared === null ? $native : TypeCombinator::union($declared, $native);
        }
        return null;
    }
}
