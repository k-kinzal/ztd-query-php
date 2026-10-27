<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Declaration;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Column values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Statement\Declaration\Column>
 */
final class ColumnGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Statement\Declaration\Column> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $name Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Type\TypeDescriptor> $type Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Type\Nullability>|null $nullability Generated constructor operand
     * @param ErisGenerator<bool>|null $generated Generated constructor operand
     * @param ErisGenerator<bool>|null $expression Generated constructor operand
     */
    public function __construct(ErisGenerator $name, ErisGenerator $type, ?ErisGenerator $nullability = null, ?ErisGenerator $generated = null, ?ErisGenerator $expression = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Statement\Declaration\Column {
            return new \SqlSemantics\Statement\Declaration\Column(name: $parts[0], type: $parts[1], nullability: $parts[2], generated: $parts[3], expression: $parts[4]);
        }, \Tests\Generator\Operands::tuple(
            $name,
            $type,
            $nullability ?? Generators::constant(\SqlSemantics\Statement\Type\Nullability::Nullable),
            $generated ?? Generators::constant(false),
            $expression ?? Generators::constant(false)
        ));
    }
}
