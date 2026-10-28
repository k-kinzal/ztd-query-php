<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Storage\Option;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes EncryptionOption values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\EncryptionOption>
 */
final class EncryptionOptionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\EncryptionOption> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Text> $encryption Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<bool>|null $equals Generated constructor operand
     */
    public function __construct(ErisGenerator $encryption, ?ErisGenerator $inputSource = null, ?ErisGenerator $equals = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\EncryptionOption {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\EncryptionOption(encryption: $parts[0], inputSource: $parts[1], equals: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $encryption,
            $inputSource ?? Generators::constant(null),
            $equals ?? Generators::bool()
        ));
    }
}
