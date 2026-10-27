<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Contract;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ParameterStyle values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Contract\ParameterStyle>
 */
final class ParameterStyleGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Contract\ParameterStyle> */
    use Composition;

    /**
     * Selects an enum alternative through Eris.
     */
    public function __construct()
    {
        $this->delegate = Generators::elements(\SqlSemantics\Contract\ParameterStyle::cases());
    }
    /**
     * Selects the alternatives valid in a particular SQL role.
     *
     * @param non-empty-list<\SqlSemantics\Contract\ParameterStyle> $cases The alternatives allowed by the parent structure
     * @return ErisGenerator<\SqlSemantics\Contract\ParameterStyle>
     */
    public static function selecting(array $cases): ErisGenerator
    {
        return Generators::elements($cases);
    }

}
