<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Contract;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes LanguageProfile values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Contract\LanguageProfile>
 */
final class LanguageProfileGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Contract\LanguageProfile> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Contract\GrammarRelease> $grammar Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Contract\LexicalSettings>|null $lexical Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Contract\ParameterStyle>|null $parameters Generated constructor operand
     */
    public function __construct(ErisGenerator $grammar, ?ErisGenerator $lexical = null, ?ErisGenerator $parameters = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Contract\LanguageProfile {
            return new \SqlSemantics\Contract\LanguageProfile(grammar: $parts[0], lexical: $parts[1], parameters: $parts[2]);
        }, Generators::tuple(
            $grammar,
            $lexical ?? Generators::constant(new \SqlSemantics\Contract\LexicalSettings()),
            $parameters ?? Generators::constant(\SqlSemantics\Contract\ParameterStyle::Native)
        ));
    }
}
