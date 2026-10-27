<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Dml\Load;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes TextFileFormat values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Dml\Load\TextFileFormat>
 */
final class TextFileFormatGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Dml\Load\TextFileFormat> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\CharsetName|null>|null $charset Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Dml\Load\FieldOption>>|null $fields Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Dml\Load\LineOption>>|null $lines Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(?ErisGenerator $charset = null, ?ErisGenerator $fields = null, ?ErisGenerator $lines = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Dml\Load\TextFileFormat {
            return new \SqlSemantics\Platform\MySql\Statement\Dml\Load\TextFileFormat(charset: $parts[0], fields: $parts[1], lines: $parts[2], inputSource: $parts[3]);
        }, \Tests\Generator\Operands::tuple(
            $charset ?? Generators::constant(null),
            $fields ?? Generators::constant([]),
            $lines ?? Generators::constant([]),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
