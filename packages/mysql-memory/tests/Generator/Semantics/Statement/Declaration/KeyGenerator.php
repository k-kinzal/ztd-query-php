<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Declaration;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Key values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Statement\Declaration\Key>
 */
final class KeyGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Statement\Declaration\Key> */
    use Composition;

    /**
     * @param ErisGenerator<bool> $primary Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Statement\Declaration\Column>> $columns Generated constructor operand
     */
    public function __construct(ErisGenerator $primary, ErisGenerator $columns)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Statement\Declaration\Key {
            return new \SqlSemantics\Statement\Declaration\Key(primary: $parts[0], columns: $parts[1]);
        }, Generators::tuple(
            $primary,
            $columns
        ));
    }
}
