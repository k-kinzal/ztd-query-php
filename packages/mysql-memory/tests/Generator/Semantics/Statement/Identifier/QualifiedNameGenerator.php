<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Identifier;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes QualifiedName values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Statement\Identifier\QualifiedName>
 */
final class QualifiedNameGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Statement\Identifier\QualifiedName> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $name Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name|null>|null $schema Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name|null>|null $catalog Generated constructor operand
     */
    public function __construct(ErisGenerator $name, ?ErisGenerator $schema = null, ?ErisGenerator $catalog = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Statement\Identifier\QualifiedName {
            return new \SqlSemantics\Statement\Identifier\QualifiedName(name: $parts[0], schema: $parts[1], catalog: $parts[2]);
        }, Generators::tuple(
            $name,
            $schema ?? Generators::constant(null),
            $catalog ?? Generators::constant(null)
        ));
    }
}
