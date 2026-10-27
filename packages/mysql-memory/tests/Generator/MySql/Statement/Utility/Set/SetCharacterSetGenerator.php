<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Utility\Set;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes SetCharacterSet values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Set\SetCharacterSet>
 */
final class SetCharacterSetGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Utility\Set\SetCharacterSet> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\CharsetName> $charset Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $charset, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Utility\Set\SetCharacterSet {
            return new \SqlSemantics\Platform\MySql\Statement\Utility\Set\SetCharacterSet(charset: $parts[0], inputSource: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $charset,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
