<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Reference\Missing;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes IncompleteMembers values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Statement\Reference\Missing\IncompleteMembers>
 */
final class IncompleteMembersGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Statement\Reference\Missing\IncompleteMembers> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Declaration\Table> $table Generated constructor operand
     */
    public function __construct(ErisGenerator $table)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Statement\Reference\Missing\IncompleteMembers {
            return new \SqlSemantics\Statement\Reference\Missing\IncompleteMembers(table: $parts[0]);
        }, Generators::tuple(
            $table
        ));
    }
}
