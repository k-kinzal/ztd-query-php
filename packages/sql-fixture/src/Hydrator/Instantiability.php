<?php

declare(strict_types=1);

namespace SqlFixture\Hydrator;

use ReflectionClass;

/**
 * Answers why a class is not one an object can be made of.
 *
 * PHP refuses an abstract class or an enum by raising an Error, which is an
 * engine failure rather than something to catch, so a hydrator has to ask
 * before it tries if it is to refuse such a class as its own kind of refusal.
 *
 * The two routes into an object disagree about what stops them: bypassing a
 * constructor works where calling a non-public one does not, and an internal
 * final class is the other way round. Each route therefore asks its own
 * question.
 */
final class Instantiability
{
    /**
     * Answers why the class cannot be built by calling its constructor.
     *
     * @param class-string $className Class the caller named
     *
     * @return string|null Why it cannot, or null when it can
     */
    public function callingConstructor(string $className): ?string
    {
        $reflection = new ReflectionClass($className);
        if ($reflection->isAbstract()) {
            return 'it is abstract';
        }
        if ($reflection->isEnum()) {
            return 'it is an enum';
        }
        if (!$reflection->isInstantiable()) {
            return 'its constructor is not public';
        }

        return null;
    }

    /**
     * Answers why the class cannot be built without calling its constructor.
     *
     * @param class-string $className Class the caller named
     *
     * @return string|null Why it cannot, or null when it can
     */
    public function bypassingConstructor(string $className): ?string
    {
        $reflection = new ReflectionClass($className);
        if ($reflection->isAbstract()) {
            return 'it is abstract';
        }
        if ($reflection->isEnum()) {
            return 'it is an enum';
        }
        if ($reflection->isInternal() && $reflection->isFinal()) {
            return 'it is an internal final class, which PHP will not build without its constructor';
        }

        return null;
    }
}
