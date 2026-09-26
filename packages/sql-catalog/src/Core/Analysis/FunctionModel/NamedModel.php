<?php

declare(strict_types=1);

namespace SqlCatalog\Core\Analysis\FunctionModel;

use RuntimeException;
use SqlCatalog\Core\Evaluation\Domain;

/**
 * Resolves a configured model using the application's Composer autoloader.
 *
 * @visibility root
 */
final class NamedModel
{
    /**
     * A callable function/static method, or a no-argument invokable class.
     *
     * @return callable(list<Domain>): ?Domain
     * @throws RuntimeException When the name does not identify a model
     */
    public static function resolve(string $name): callable
    {
        if (is_callable($name)) {
            return $name;
        }
        if (class_exists($name)) {
            $model = new $name();
            if (is_callable($model)) {
                return $model;
            }
        }

        throw new RuntimeException(sprintf('Function model "%s" must be an autoloadable callable or invokable class.', $name));
    }
}
