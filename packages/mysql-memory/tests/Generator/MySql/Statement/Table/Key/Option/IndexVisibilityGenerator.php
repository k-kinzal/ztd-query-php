<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table\Key\Option;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes IndexVisibility values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexVisibility>
 */
final class IndexVisibilityGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexVisibility> */
    use Composition;

    /**
     * @param ErisGenerator<bool> $visible Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $visible, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexVisibility {
            return new \SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexVisibility(visible: $parts[0], inputSource: $parts[1]);
        }, Generators::tuple(
            $visible,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
