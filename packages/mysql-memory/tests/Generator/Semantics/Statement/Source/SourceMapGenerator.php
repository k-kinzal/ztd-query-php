<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Source;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes SourceMap values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Statement\Source\SourceMap>
 */
final class SourceMapGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Statement\Source\SourceMap> */
    use Composition;

    /**
     * @param ErisGenerator<list<\SqlSemantics\Statement\Source\Origin>>|null $origins Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Statement\Source\SourceNotice>>|null $notices Generated constructor operand
     */
    public function __construct(?ErisGenerator $origins = null, ?ErisGenerator $notices = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Statement\Source\SourceMap {
            return new \SqlSemantics\Statement\Source\SourceMap(origins: $parts[0], notices: $parts[1]);
        }, Generators::tuple(
            $origins ?? Generators::constant([]),
            $notices ?? Generators::constant([])
        ));
    }
}
