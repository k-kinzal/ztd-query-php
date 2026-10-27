<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Alter\Command;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ConvertCharset values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Alter\Command\ConvertCharset>
 */
final class ConvertCharsetGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Alter\Command\ConvertCharset> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\CharsetName> $charset Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\CollationName|null>|null $collation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $charset, ?ErisGenerator $collation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Alter\Command\ConvertCharset {
            return new \SqlSemantics\Platform\MySql\Statement\Alter\Command\ConvertCharset(charset: $parts[0], collation: $parts[1], inputSource: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $charset,
            $collation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
