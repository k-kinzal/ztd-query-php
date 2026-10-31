<?php

declare(strict_types=1);

namespace Rules;

use PHPStan\Reflection\ClassReflection;

/**
 * Recognizes semantic properties that PHPUnit will discover and execute.
 *
 * @visibility Rules
 */
final class RunnableProperty
{
    /**
     * A declaration counts only when PHPUnit can discover the property; an optional name selects one method.
     */
    public function accepts(ClassReflection $class, ?string $methodName = null): bool
    {
        if ($class->isAbstract()) {
            return false;
        }
        foreach ($class->getNativeReflection()->getMethods() as $method) {
            if ($methodName !== null && $method->getName() !== $methodName) {
                continue;
            }
            if ($method->getDeclaringClass()->getName() !== $class->getName() || !str_starts_with($method->getName(), 'satisfies') || !$method->isPublic() || $method->isStatic()) {
                continue;
            }
            foreach ($method->getAttributes() as $attribute) {
                if ($attribute->getName() === 'PHPUnit\\Framework\\Attributes\\Test') {
                    return true;
                }
            }
        }
        return false;
    }
}
