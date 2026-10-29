<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\Option;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes TlsRequirement values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Option\TlsRequirement>
 */
final class TlsRequirementGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Account\Option\TlsRequirement> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Option\TlsKind> $kind Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Account\Option\TlsCondition>>|null $conditions Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $kind, ?ErisGenerator $conditions = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Account\Option\TlsRequirement {
            return new \SqlSemantics\Platform\MySql\Statement\Account\Option\TlsRequirement(kind: $parts[0], conditions: $parts[1], inputSource: $parts[2]);
        }, Generators::tuple(
            $kind,
            $conditions ?? Generators::constant([]),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
