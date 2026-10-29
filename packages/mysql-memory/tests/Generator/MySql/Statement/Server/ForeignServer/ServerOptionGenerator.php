<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\ForeignServer;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ServerOption values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\ServerOption>
 */
final class ServerOptionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\ServerOption> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\ServerOptionKind> $kind Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Text|\SqlSemantics\Platform\MySql\Statement\Literal\Numeral> $value Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $kind, ErisGenerator $value, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\ServerOption {
            return new \SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\ServerOption(kind: $parts[0], value: $parts[1], inputSource: $parts[2]);
        }, Generators::tuple(
            $kind,
            $value,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
