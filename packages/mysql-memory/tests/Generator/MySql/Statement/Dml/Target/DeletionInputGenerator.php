<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml\Target;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes DeletionInput values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Target\DeletionInput>
 */
final class DeletionInputGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Dml\Target\DeletionInput> */
    use Composition;

    /**
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Relation\TableSource>> $tables Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Statement\Identifier\QualifiedName>|null>|null $targets Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Relation\TableSource|null>|null>|null $resolutions Generated constructor operand
     */
    public function __construct(ErisGenerator $tables, ?ErisGenerator $targets = null, ?ErisGenerator $resolutions = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Dml\Target\DeletionInput {
            return new \SqlSemantics\Platform\MySql\Statement\Dml\Target\DeletionInput(tables: $parts[0], targets: $parts[1], resolutions: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $tables,
            $targets ?? Generators::constant(null),
            $resolutions ?? Generators::constant(null)
        ));
    }
}
