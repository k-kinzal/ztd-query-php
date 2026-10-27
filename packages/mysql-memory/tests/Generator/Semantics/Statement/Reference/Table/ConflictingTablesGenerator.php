<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Reference\Table;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ConflictingTables values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Statement\Reference\Table\ConflictingTables>
 */
final class ConflictingTablesGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Statement\Reference\Table\ConflictingTables> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\QualifiedName> $name Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Statement\Declaration\Table>> $candidates Generated constructor operand
     */
    public function __construct(ErisGenerator $name, ErisGenerator $candidates)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Statement\Reference\Table\ConflictingTables {
            return new \SqlSemantics\Statement\Reference\Table\ConflictingTables(name: $parts[0], candidates: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $name,
            $candidates
        ));
    }
}
