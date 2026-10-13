<?php

declare(strict_types=1);

namespace Fuzz\Structure;

use JsonException;
use RuntimeException;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Statement\Scalar;

/**
 * Discovers concrete types from the reviewed semantic value inventories.
 */
final class Catalogue
{
    /**
     * @var list<class-string>
     */
    public readonly array $classes;

    /**
     * Loads the complete core and MySQL value inventories.
     *
     * @throws RuntimeException When an inventory is missing or invalid
     * @throws JsonException When an inventory is not JSON
     */
    public function __construct()
    {
        $classes = [];
        foreach (['sql-semantics', 'sql-semantics-mysql'] as $package) {
            $text = file_get_contents(dirname(__DIR__, 2) . '/vendor/k-kinzal/' . $package . '/resources/value-schema.json');
            if ($text === false) {
                throw new RuntimeException('Cannot read the semantic value inventory.');
            }
            $schema = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($schema)) {
                throw new RuntimeException('The semantic value inventory is not an object.');
            }
            foreach (array_keys($schema) as $class) {
                if (!is_string($class) || !class_exists($class)) {
                    throw new RuntimeException('An inventoried semantic type cannot be loaded.');
                }
                if (str_starts_with($class, 'SqlSemantics\\Statement\\') || str_starts_with($class, 'SqlSemantics\\Platform\\MySql\\Statement\\')) {
                    $classes[] = $class;
                }
            }
        }
        sort($classes);
        $this->classes = $classes;
    }

    /**
     * @return list<class-string>
     */
    public function roots(): array
    {
        return array_values(array_filter($this->classes, static fn (string $class): bool => is_a($class, Statement::class, true) || is_a($class, Scalar::class, true)));
    }

    /**
     * @param class-string $type
     * @return list<class-string>
     */
    public function implementations(string $type): array
    {
        return array_values(array_filter($this->classes, static fn (string $class): bool => is_a($class, $type, true)));
    }
}
