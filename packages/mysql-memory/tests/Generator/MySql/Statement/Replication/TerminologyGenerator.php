<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Replication;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Terminology values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Terminology>
 */
final class TerminologyGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Replication\Terminology> */
    use Composition;

    /**
     * Selects an enum alternative through Eris.
     */
    public function __construct()
    {
        $this->delegate = Generators::elements(\SqlSemantics\Platform\MySql\Statement\Replication\Terminology::cases());
    }
    /**
     * Selects the alternatives valid in a particular SQL role.
     *
     * @param non-empty-list<\SqlSemantics\Platform\MySql\Statement\Replication\Terminology> $cases The alternatives allowed by the parent structure
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Terminology>
     */
    public static function selecting(array $cases): ErisGenerator
    {
        return Generators::elements($cases);
    }

}
