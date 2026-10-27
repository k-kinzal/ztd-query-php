<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Plugin;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes UninstallComponent values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Plugin\UninstallComponent>
 */
final class UninstallComponentGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Plugin\UninstallComponent> */
    use Composition;

    /**
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Literal\Text>> $components Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $components, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Plugin\UninstallComponent {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Plugin\UninstallComponent(components: $parts[0], statementInformation: $parts[1], inputSource: $parts[2]);
        }, \Tests\Generator\Operands::tuple(
            $components,
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Plugin\UninstallComponent>>
     */
    public static function forms(): iterable
    {
        yield 'UninstallComponent/422' => new UninstallComponentGenerator(
            components: \Tests\Generator\Operands::tuple(
                new \Tests\Generator\MySql\Statement\Literal\TextGenerator(
                    value: Generators::constant('text'),
                    radix: Generators::constant(null)
                )
            )
        );
    }

}
