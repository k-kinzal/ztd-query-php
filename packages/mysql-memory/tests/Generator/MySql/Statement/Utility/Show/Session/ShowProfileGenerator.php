<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Utility\Show\Session;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ShowProfile values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Show\Session\ShowProfile>
 */
final class ShowProfileGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Utility\Show\Session\ShowProfile> */
    use Composition;

    /**
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Utility\Show\Session\ProfileSection>>|null $sections Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Numeral|null>|null $query Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Limit|null>|null $limit Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(?ErisGenerator $sections = null, ?ErisGenerator $query = null, ?ErisGenerator $limit = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Utility\Show\Session\ShowProfile {
            return new \SqlSemantics\Platform\MySql\Statement\Utility\Show\Session\ShowProfile(sections: $parts[0], query: $parts[1], limit: $parts[2], statementInformation: $parts[3], inputSource: $parts[4]);
        }, \Tests\Generator\Operands::tuple(
            $sections ?? Generators::constant([]),
            $query ?? Generators::constant(null),
            $limit ?? Generators::constant(null),
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Show\Session\ShowProfile>>
     */
    public static function forms(): iterable
    {
        yield 'ShowProfile/109' => new ShowProfileGenerator(
            sections: Generators::constant([]),
            query: Generators::constant(null),
            limit: Generators::constant(null)
        );
        yield 'ShowProfile/364' => new ShowProfileGenerator(
            sections: \Tests\Generator\Operands::tuple(
                ProfileSectionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Utility\Show\Session\ProfileSection::Cpu])
            ),
            query: Generators::constant(null),
            limit: Generators::constant(null)
        );
    }

}
