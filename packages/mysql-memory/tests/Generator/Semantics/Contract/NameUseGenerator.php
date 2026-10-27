<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Contract;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes NameUse values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Contract\NameUse>
 */
final class NameUseGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Contract\NameUse> */
    use Composition;

    /**
     * Selects an enum alternative through Eris.
     */
    public function __construct()
    {
        $this->delegate = Generators::elements(\SqlSemantics\Contract\NameUse::cases());
    }
    /**
     * Selects the alternatives valid in a particular SQL role.
     *
     * @param non-empty-list<\SqlSemantics\Contract\NameUse> $cases The alternatives allowed by the parent structure
     * @return ErisGenerator<\SqlSemantics\Contract\NameUse>
     */
    public static function selecting(array $cases): ErisGenerator
    {
        return Generators::elements($cases);
    }

}
