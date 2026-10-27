<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Reference\Missing;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes SessionState values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Statement\Reference\Missing\SessionState>
 */
final class SessionStateGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Statement\Reference\Missing\SessionState> */
    use Composition;

    /**
     * @param ErisGenerator<string> $subject Generated constructor operand
     */
    public function __construct(ErisGenerator $subject)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Statement\Reference\Missing\SessionState {
            return new \SqlSemantics\Statement\Reference\Missing\SessionState(subject: $parts[0]);
        }, \Tests\Generator\Operands::tuple(
            $subject
        ));
    }
}
