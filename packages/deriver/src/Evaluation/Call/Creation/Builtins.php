<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Call\Creation;

/**
 * Describes the supported PHP 8.3 native throwable hierarchy without host reflection.
 * @visibility root
 */
final class Builtins
{
    /**
     * @return array<string, string> Canonical native class names and direct parents
     */
    public function hierarchy(): array
    {
        return [
            'stdClass' => '', 'Throwable' => '', 'Exception' => 'Throwable', 'Error' => 'Throwable',
            'ErrorException' => 'Exception', 'LogicException' => 'Exception', 'RuntimeException' => 'Exception',
            'BadFunctionCallException' => 'LogicException', 'BadMethodCallException' => 'BadFunctionCallException',
            'DomainException' => 'LogicException', 'InvalidArgumentException' => 'LogicException',
            'LengthException' => 'LogicException', 'OutOfRangeException' => 'LogicException',
            'OutOfBoundsException' => 'RuntimeException', 'OverflowException' => 'RuntimeException',
            'RangeException' => 'RuntimeException', 'UnderflowException' => 'RuntimeException',
            'UnexpectedValueException' => 'RuntimeException', 'Random\\RandomException' => 'Exception',
            'TypeError' => 'Error', 'ArgumentCountError' => 'TypeError', 'ValueError' => 'Error',
            'ArithmeticError' => 'Error', 'DivisionByZeroError' => 'ArithmeticError', 'ParseError' => 'Error',
            'AssertionError' => 'Error', 'UnhandledMatchError' => 'Error', 'FiberError' => 'Error',
        ];
    }

    /**
     * Resolves case-insensitive PHP native class spelling.
     * @param string $name Requested class
     * @return string|null Canonical supported class name
     */
    public function name(string $name): ?string
    {
        foreach ($this->hierarchy() as $canonical => $_) {
            if (strcasecmp($name, $canonical) === 0) {
                return $canonical;
            }
        }
        return null;
    }

    /**
     * Returns a known native parent while preserving case-insensitive class resolution.
     * @param string $name Runtime class
     * @return string Parent class or interface
     */
    public function parent(string $name): string
    {
        return $this->hierarchy()[$this->name($name) ?? ''] ?? '';
    }
}
