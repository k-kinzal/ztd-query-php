<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Instance;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ReloadTls values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Instance\ReloadTls>
 */
final class ReloadTlsGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Instance\ReloadTls> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name|null>|null $channel Generated constructor operand
     * @param ErisGenerator<bool>|null $noRollbackOnError Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(?ErisGenerator $channel = null, ?ErisGenerator $noRollbackOnError = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Instance\ReloadTls {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Instance\ReloadTls(channel: $parts[0], noRollbackOnError: $parts[1], inputSource: $parts[2]);
        }, Generators::tuple(
            $channel ?? Generators::constant(null),
            $noRollbackOnError ?? Generators::constant(false),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
