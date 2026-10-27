<?php

declare(strict_types=1);

namespace Tests\Generator\MySql;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Mode values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Mode>
 */
final class ModeGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Mode> */
    use Composition;

    /**
     * @param ErisGenerator<bool>|null $ansiQuotes Generated constructor operand
     * @param ErisGenerator<bool>|null $pipesAsConcat Generated constructor operand
     * @param ErisGenerator<bool>|null $highNotPrecedence Generated constructor operand
     * @param ErisGenerator<bool>|null $noBackslashEscapes Generated constructor operand
     * @param ErisGenerator<bool>|null $ignoreSpace Generated constructor operand
     */
    public function __construct(?ErisGenerator $ansiQuotes = null, ?ErisGenerator $pipesAsConcat = null, ?ErisGenerator $highNotPrecedence = null, ?ErisGenerator $noBackslashEscapes = null, ?ErisGenerator $ignoreSpace = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Mode {
            return new \SqlSemantics\Platform\MySql\Mode(ansiQuotes: $parts[0], pipesAsConcat: $parts[1], highNotPrecedence: $parts[2], noBackslashEscapes: $parts[3], ignoreSpace: $parts[4]);
        }, \Tests\Generator\Operands::tuple(
            $ansiQuotes ?? Generators::constant(false),
            $pipesAsConcat ?? Generators::constant(false),
            $highNotPrecedence ?? Generators::constant(false),
            $noBackslashEscapes ?? Generators::constant(false),
            $ignoreSpace ?? Generators::constant(false)
        ));
    }
}
