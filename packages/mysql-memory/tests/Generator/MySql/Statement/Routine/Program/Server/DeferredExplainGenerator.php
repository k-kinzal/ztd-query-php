<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program\Server;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes DeferredExplain values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Server\DeferredExplain>
 */
final class DeferredExplainGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Server\DeferredExplain> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Explainable|\SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredQuery|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredInsertRows|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredInsertSet|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredInsertQuery|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredDelete|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredMultipleDelete> $statement Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name|null>|null $format Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Variable\UserVariable|null>|null $into Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name|null>|null $database Generated constructor operand
     * @param ErisGenerator<bool>|null $analyze Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainModifier|null>|null $modifier Generated constructor operand
     * @param ErisGenerator<bool>|null $emptyInto Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainVerb>|null $verb Generated command choice
     */
    public function __construct(ErisGenerator $statement, ?ErisGenerator $format = null, ?ErisGenerator $into = null, ?ErisGenerator $database = null, ?ErisGenerator $analyze = null, ?ErisGenerator $modifier = null, ?ErisGenerator $emptyInto = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $verb = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\Program\Server\DeferredExplain {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Server\DeferredExplain(statement: $parts[0], format: $parts[1], into: $parts[2], database: $parts[3], analyze: $parts[4], modifier: $parts[5], emptyInto: $parts[6], inputSource: $parts[7], verb: $parts[8]);
        }, Generators::tuple(
            $statement,
            $format ?? Generators::constant(null),
            $into ?? Generators::constant(null),
            $database ?? Generators::constant(null),
            $analyze ?? Generators::constant(false),
            $modifier ?? Generators::constant(null),
            $emptyInto ?? Generators::constant(false),
            $inputSource ?? Generators::constant(null),
            $verb ?? new \Tests\Generator\MySql\Statement\Utility\Explain\ExplainVerbGenerator()
        ));
    }
}
