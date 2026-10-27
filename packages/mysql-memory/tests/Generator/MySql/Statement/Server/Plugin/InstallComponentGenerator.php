<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Plugin;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes InstallComponent values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Plugin\InstallComponent>
 */
final class InstallComponentGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Plugin\InstallComponent> */
    use Composition;

    /**
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Literal\Text>> $components Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Server\Plugin\ComponentSetting>>|null $settings Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $components, ?ErisGenerator $settings = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Plugin\InstallComponent {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Plugin\InstallComponent(components: $parts[0], settings: $parts[1], statementInformation: $parts[2], inputSource: $parts[3]);
        }, \Tests\Generator\Operands::tuple(
            $components,
            $settings ?? Generators::constant([]),
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Plugin\InstallComponent>>
     */
    public static function forms(): iterable
    {
        yield 'InstallComponent/53' => new InstallComponentGenerator(
            components: \Tests\Generator\Operands::tuple(
                new \Tests\Generator\MySql\Statement\Literal\TextGenerator(
                    value: Generators::constant('text'),
                    radix: Generators::constant(null)
                )
            ),
            settings: Generators::constant([])
        );
        yield 'InstallComponent/420' => new InstallComponentGenerator(
            components: \Tests\Generator\Operands::tuple(
                new \Tests\Generator\MySql\Statement\Literal\TextGenerator(
                    value: Generators::constant('text'),
                    radix: Generators::constant(null)
                )
            ),
            settings: \Tests\Generator\Operands::tuple(
                new ComponentSettingGenerator(
                    variable: new \Tests\Generator\MySql\Statement\Variable\SystemTargetGenerator(
                        name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                            quote: Generators::constant(null)
                        ),
                        scope: Generators::constant(null),
                        instance: Generators::constant(null)
                    ),
                    value: new OnWordGenerator()
                )
            )
        );
    }

}
