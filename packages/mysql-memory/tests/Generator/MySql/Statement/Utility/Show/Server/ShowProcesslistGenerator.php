<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Utility\Show\Server;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ShowProcesslist values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowProcesslist>
 */
final class ShowProcesslistGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowProcesslist> */
    use Composition;

    /**
     * @param ErisGenerator<bool>|null $full Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(?ErisGenerator $full = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowProcesslist {
            return new \SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowProcesslist(full: $parts[0], statementInformation: $parts[1], inputSource: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $full ?? Generators::constant(false),
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowProcesslist>>
     */
    public static function forms(): iterable
    {
        yield 'ShowProcesslist/108' => new ShowProcesslistGenerator(
            full: Generators::constant(false)
        );
    }

}
