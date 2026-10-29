<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ExternalBody values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\ExternalBody>
 */
final class ExternalBodyGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\ExternalBody> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\DollarQuotedText|\SqlSemantics\Platform\MySql\Statement\Literal\Text> $code Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $code, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\ExternalBody {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\ExternalBody(code: $parts[0], inputSource: $parts[1]);
        }, Generators::tuple(
            $code,
            $inputSource ?? Generators::constant(null)
        ));
    }
}
