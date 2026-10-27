<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Instance;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RotateMasterKey values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Instance\RotateMasterKey>
 */
final class RotateMasterKeyGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Instance\RotateMasterKey> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $keyring Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $keyring, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Instance\RotateMasterKey {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Instance\RotateMasterKey(keyring: $parts[0], inputSource: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $keyring,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
