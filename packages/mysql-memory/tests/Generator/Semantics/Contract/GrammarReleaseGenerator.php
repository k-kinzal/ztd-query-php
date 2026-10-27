<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Contract;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes GrammarRelease values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Contract\GrammarRelease>
 */
final class GrammarReleaseGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Contract\GrammarRelease> */
    use Composition;

    /**
     * Selects an enum alternative through Eris.
     */
    public function __construct()
    {
        $this->delegate = Generators::elements(\SqlSemantics\Contract\GrammarRelease::cases());
    }
    /**
     * Selects the alternatives valid in a particular SQL role.
     *
     * @param non-empty-list<\SqlSemantics\Contract\GrammarRelease> $cases The alternatives allowed by the parent structure
     * @return ErisGenerator<\SqlSemantics\Contract\GrammarRelease>
     */
    public static function selecting(array $cases): ErisGenerator
    {
        return Generators::elements($cases);
    }

}
