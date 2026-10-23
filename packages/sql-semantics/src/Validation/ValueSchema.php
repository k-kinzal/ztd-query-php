<?php

declare(strict_types=1);

namespace SqlSemantics\Validation;

use JsonException;
use ReflectionClass;
use SqlSemantics\Diagnostic\Check;

/**
 * Checks every retained field against the package's explicit semantic value schema.
 *
 * A new value class or field is refused until its schema is reviewed. The graph audit
 * separately checks every value, including elements of arrays and private fields. The
 * schema describes retained data only: methods may compute from that data freely.
 *
 * @visibility SqlSemantics
 */
final class ValueSchema
{
    /**
     * @var array<string, array<string, array<string, string>>>
     */
    private static array $schemas = [];

    /**
     * @var array<class-string, true>
     */
    private static array $checked = [];

    /**
     * Refuses a value class whose retained fields differ from its explicit schema.
     *
     * @param class-string $class
     */
    public function check(string $class): void
    {
        $name = $class;
        if (isset(self::$checked[$name])) {
            return;
        }
        $schema = $this->schema($this->directory($name));
        Check::input(isset($schema[$name]), 'Semantic value class ' . $name . ' has no retained-field schema.');
        Check::input($schema[$name] === $this->fields($class), 'Retained fields differ from the semantic value schema of ' . $name . '.');
        self::$checked[$name] = true;
    }

    /**
     * Describes every retained field, including private fields, in stable name order.
     *
     * @param class-string $class
     * @return array<string, string>
     */
    public function fields(string $class): array
    {
        $reflection = new ReflectionClass($class);
        Check::input($reflection->getParentClass() === false, 'Semantic value classes do not inherit hidden state.');
        $fields = [];
        foreach ($reflection->getProperties() as $property) {
            $fields[$property->getName()] = (string) $property->getType();
        }
        ksort($fields);

        return $fields;
    }

    /**
     * Finds the installed package that owns a semantic value.
     *
     * @param class-string $class
     */
    public function directory(string $class): string
    {
        return \SqlSemantics\Contract\Platforms::valueDirectory($class);
    }

    /**
     * Loads the reviewed retained-field schema of a package.
     *
     * @throws \SqlSemantics\Diagnostic\InvariantViolation When the installed schema is malformed
     *
     * @return array<string, array<string, string>>
     */
    public function schema(string $directory): array
    {
        if (!isset(self::$schemas[$directory])) {
            $text = file_get_contents($directory . '/resources/value-schema.json');
            Check::input($text !== false, 'The semantic value schema must be installed.');
            try {
                /** @var array<string, array<string, string>> $schema */
                $schema = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $failure) {
                throw new \SqlSemantics\Diagnostic\InvariantViolation('The semantic value schema is malformed.', 0, $failure);
            }
            self::$schemas[$directory] = $schema;
        }

        return self::$schemas[$directory];
    }
}
