<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Contract;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes LexicalSettings values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Contract\LexicalSettings>
 */
final class LexicalSettingsGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Contract\LexicalSettings> */
    use Composition;

    /**
     * @param ErisGenerator<string>|null $settings Generated constructor operand
     */
    public function __construct(?ErisGenerator $settings = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Contract\LexicalSettings {
            return new \SqlSemantics\Contract\LexicalSettings(settings: $parts[0]);
        }, Generators::tuple(
            $settings ?? Generators::constant('')
        ));
    }
}
