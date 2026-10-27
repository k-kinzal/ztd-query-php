<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Replication\Group;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes CredentialOption values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Group\CredentialOption>
 */
final class CredentialOptionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Replication\Group\CredentialOption> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Replication\Group\Credential> $credential Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Text> $value Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $credential, ErisGenerator $value, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Replication\Group\CredentialOption {
            return new \SqlSemantics\Platform\MySql\Statement\Replication\Group\CredentialOption(credential: $parts[0], value: $parts[1], inputSource: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $credential,
            $value,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
