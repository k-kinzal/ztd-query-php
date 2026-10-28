<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Database;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes DatabaseEncryption values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseEncryption>
 */
final class DatabaseEncryptionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseEncryption> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Database\Encryption|\SqlSemantics\Platform\MySql\Statement\Literal\Text> $encryption Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Text|null>|null $spelling Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<bool>|null $equals Generated constructor operand
     * @param ErisGenerator<bool>|null $defaultKeyword Generated constructor operand
     */
    public function __construct(ErisGenerator $encryption, ?ErisGenerator $spelling = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $equals = null, ?ErisGenerator $defaultKeyword = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseEncryption {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseEncryption(encryption: $parts[0], spelling: $parts[1], inputSource: $parts[2], equals: $parts[3], defaultKeyword: $parts[4]);
        }, \Tests\Generator\Operands::tuple(
            $encryption,
            $spelling ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null),
            $equals ?? Generators::bool(),
            $defaultKeyword ?? Generators::bool()
        ));
    }
}
