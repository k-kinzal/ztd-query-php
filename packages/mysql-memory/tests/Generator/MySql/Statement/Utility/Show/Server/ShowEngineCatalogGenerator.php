<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Utility\Show\Server;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes ShowEngineCatalog values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowEngineCatalog>
 */
final class ShowEngineCatalogGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowEngineCatalog> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<bool>|null $storage The generated written alternative
     */
    public function __construct(?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $storage = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowEngineCatalog {
            return new \SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowEngineCatalog(statementInformation: $parts[0], inputSource: $parts[1], storage: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null),
            $storage ?? Generators::bool()
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowEngineCatalog>>
     */
    public static function forms(): iterable
    {
        yield 'ShowEngineCatalog/95' => new ShowEngineCatalogGenerator();
    }

}
