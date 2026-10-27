<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Utility\Show\Server;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ShowCollation values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowCollation>
 */
final class ShowCollationGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowCollation> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowLike|\SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowWhere|null>|null $filter Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\RelationFact|null>|null $relationInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(?ErisGenerator $filter = null, ?ErisGenerator $relationInformation = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowCollation {
            return new \SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowCollation(filter: $parts[0], relationInformation: $parts[1], statementInformation: $parts[2], inputSource: $parts[3]);
        }, \Tests\Generator\Operands::tuple(
            $filter ?? Generators::constant(null),
            $relationInformation ?? Generators::constant(null),
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowCollation>>
     */
    public static function forms(): iterable
    {
        yield 'ShowCollation/79' => new ShowCollationGenerator(
            filter: Generators::constant(null)
        );
    }

}
