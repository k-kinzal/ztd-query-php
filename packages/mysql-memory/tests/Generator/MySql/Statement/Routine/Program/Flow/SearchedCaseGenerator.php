<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program\Flow;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes SearchedCase values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\SearchedCase>
 */
final class SearchedCaseGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\SearchedCase> */
    use Composition;

    /**
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ConditionalBranch>> $branches Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramStatement|\SqlSemantics\Platform\MySql\Statement\Statement>>|null $otherwise Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $branches, ?ErisGenerator $otherwise = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\SearchedCase {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\SearchedCase(branches: $parts[0], otherwise: $parts[1], inputSource: $parts[2]);
        }, Generators::tuple(
            $branches,
            $otherwise ?? Generators::constant([]),
            $inputSource ?? Generators::constant(null)
        ));
    }
}
