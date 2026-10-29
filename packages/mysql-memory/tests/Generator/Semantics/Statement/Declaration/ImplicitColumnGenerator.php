<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Declaration;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ImplicitColumn values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Statement\Declaration\ImplicitColumn>
 */
final class ImplicitColumnGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Statement\Declaration\ImplicitColumn> */
    use Composition;

    /**
     * @param ErisGenerator<list<\SqlSemantics\Statement\Identifier\Name>> $names Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Declaration\Column> $column Generated constructor operand
     */
    public function __construct(ErisGenerator $names, ErisGenerator $column)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Statement\Declaration\ImplicitColumn {
            return new \SqlSemantics\Statement\Declaration\ImplicitColumn(names: $parts[0], column: $parts[1]);
        }, Generators::tuple(
            $names,
            $column
        ));
    }
}
