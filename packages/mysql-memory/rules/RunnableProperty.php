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
     * A declaration counts only when PHPUnit can actually discover and run its property.
     */
    public function accepts(ClassReflection $class): bool
    {
        if ($class->isAbstract() || !$class->hasNativeMethod('satisfiesPublicValueInvariants')) {
            return false;
        }
        $method = $class->getNativeMethod('satisfiesPublicValueInvariants');
        if (!$method->isPublic() || $method->isStatic()) {
            return false;
        }
        foreach ($method->getAttributes() as $attribute) {
            if ($attribute->getName() === 'PHPUnit\\Framework\\Attributes\\Test') {
                return true;
            }
        }
        return false;
    }
}
