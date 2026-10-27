<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Type\Resolved;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Collation values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation>
 */
final class CollationGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation> */
    use Composition;

    /**
     * @param ErisGenerator<string> $name Generated constructor operand
     * @param ErisGenerator<int> $id Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset> $charset Generated constructor operand
     * @param ErisGenerator<bool> $padSpace Generated constructor operand
     */
    public function __construct(ErisGenerator $name, ErisGenerator $id, ErisGenerator $charset, ErisGenerator $padSpace)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation {
            return new \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation(name: $parts[0], id: $parts[1], charset: $parts[2], padSpace: $parts[3]);
        }, \Tests\Generator\Operands::tuple(
            $name,
            $id,
            $charset,
            $padSpace
        ));
    }
}
