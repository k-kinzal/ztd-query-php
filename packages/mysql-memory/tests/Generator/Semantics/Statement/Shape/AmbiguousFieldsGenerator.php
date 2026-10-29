<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Shape;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes AmbiguousFields values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Statement\Shape\AmbiguousFields>
 */
final class AmbiguousFieldsGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Statement\Shape\AmbiguousFields> */
    use Composition;

    /**
     * @param ErisGenerator<string> $name Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Statement\Shape\Field>> $fields Generated constructor operand
     */
    public function __construct(ErisGenerator $name, ErisGenerator $fields)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Statement\Shape\AmbiguousFields {
            return new \SqlSemantics\Statement\Shape\AmbiguousFields(name: $parts[0], fields: $parts[1]);
        }, Generators::tuple(
            $name,
            $fields
        ));
    }
}
