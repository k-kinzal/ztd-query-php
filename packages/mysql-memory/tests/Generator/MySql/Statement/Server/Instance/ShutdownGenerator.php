<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Instance;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Shutdown values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Instance\Shutdown>
 */
final class ShutdownGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Instance\Shutdown> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Instance\Shutdown {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Instance\Shutdown(statementInformation: $parts[0], inputSource: $parts[1]);
        }, \Tests\Generator\Operands::tuple(
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Instance\Shutdown>>
     */
    public static function forms(): iterable
    {
        yield 'Shutdown/120' => new ShutdownGenerator();
    }

}
