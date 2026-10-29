<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Shape;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes OutputSlot values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Statement\Shape\OutputSlot>
 */
final class OutputSlotGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Statement\Shape\OutputSlot> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name|null> $name Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Type\TypeFact> $type Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Type\Nullability> $nullability Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Declaration\Column|null>|null $column Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Shape\OutputSlot|null>|null $origin Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Statement\Reference\Missing\MissingInput>>|null $unnamed Generated constructor operand
     */
    public function __construct(ErisGenerator $name, ErisGenerator $type, ErisGenerator $nullability, ?ErisGenerator $column = null, ?ErisGenerator $origin = null, ?ErisGenerator $unnamed = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Statement\Shape\OutputSlot {
            return new \SqlSemantics\Statement\Shape\OutputSlot(name: $parts[0], type: $parts[1], nullability: $parts[2], column: $parts[3], origin: $parts[4], unnamed: $parts[5]);
        }, Generators::tuple(
            $name,
            $type,
            $nullability,
            $column ?? Generators::constant(null),
            $origin ?? Generators::constant(null),
            $unnamed ?? Generators::constant([])
        ));
    }
}
