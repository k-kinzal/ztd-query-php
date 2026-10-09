<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Type\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A declared type whose width, precision or scale is invalid.
 *
 * The name is empty for routine parameters and return types; table columns
 * retain their names. Source:
 * https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html.
 *
 * @visibility public
 * @example Describing an invalid return width
 *     (new \SqlSemantics\Platform\MySql\Statement\Type\Problem\InvalidTypeSize(\SqlSemantics\Platform\MySql\Statement\Type\Problem\TypeLimit::Width, '', 256, 255))->message() // => "Display width out of range for column '' (max = 255)"
 */
final class InvalidTypeSize implements Diagnostic
{
    use Snapshot;

    /**
     * @param TypeLimit $rule The bound violated
     * @param string $name The column name, or an empty name for a routine type
     * @param int $actual The requested size
     * @param int $maximum The maximum accepted size
     */
    public function __construct(public readonly TypeLimit $rule, public readonly string $name, public readonly int $actual = 0, public readonly int $maximum = 0)
    {
    }

    /**
     * Describes the problem as the server does.
     */
    public function message(): string
    {
        return match ($this->rule) {
            TypeLimit::Width => "Display width out of range for column '{$this->name}' (max = {$this->maximum})",
            TypeLimit::Precision => "Too-big precision {$this->actual} specified for '{$this->name}'. Maximum is {$this->maximum}.",
            TypeLimit::Scale => "Too big scale {$this->actual} specified for column '{$this->name}'. Maximum is {$this->maximum}.",
            TypeLimit::ScaleExceedsPrecision => "For float(M,D), double(M,D) or decimal(M,D), M must be >= D (column '{$this->name}').",
            TypeLimit::Empty => "Invalid size for column '{$this->name}'.",
            TypeLimit::Specifier => "Incorrect column specifier for column '{$this->name}'",
        };
    }
}
