<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program\Server;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes DeferredGroupStart values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Server\DeferredGroupStart>
 */
final class DeferredGroupStartGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Server\DeferredGroupStart> */
    use Composition;

    /**
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Replication\Group\CredentialOption>> $options Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $options, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\Program\Server\DeferredGroupStart {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Server\DeferredGroupStart(options: $parts[0], inputSource: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $options,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
