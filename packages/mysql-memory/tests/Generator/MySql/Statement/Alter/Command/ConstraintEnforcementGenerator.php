<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Alter\Command;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ConstraintEnforcement values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Alter\Command\ConstraintEnforcement>
 */
final class ConstraintEnforcementGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Alter\Command\ConstraintEnforcement> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Alter\Command\ElementKind> $kind Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $constraint Generated constructor operand
     * @param ErisGenerator<bool> $enforced Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $kind, ErisGenerator $constraint, ErisGenerator $enforced, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Alter\Command\ConstraintEnforcement {
            return new \SqlSemantics\Platform\MySql\Statement\Alter\Command\ConstraintEnforcement(kind: $parts[0], constraint: $parts[1], enforced: $parts[2], inputSource: $parts[3]);
        }, Generators::tuple(
            $kind,
            $constraint,
            $enforced,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
