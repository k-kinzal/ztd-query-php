<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program\Expression\Call;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes DormantCall values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DormantCall>
 */
final class DormantCallGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DormantCall> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $name Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Call\CallArgument|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredCallArgument>>|null $arguments Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\ScalarFact|null>|null $scalarInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $name, ?ErisGenerator $arguments = null, ?ErisGenerator $scalarInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DormantCall {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DormantCall(name: $parts[0], arguments: $parts[1], scalarInformation: $parts[2], inputSource: $parts[3]);
        }, \Tests\Generator\Operands::tuple(
            $name,
            $arguments ?? Generators::constant([]),
            $scalarInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
