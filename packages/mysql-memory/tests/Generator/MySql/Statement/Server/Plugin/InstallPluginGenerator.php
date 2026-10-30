<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Plugin;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use LogicException;
use Tests\Generator\Composition;

/**
 * Composes InstallPlugin values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Plugin\InstallPlugin>
 */
final class InstallPluginGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Plugin\InstallPlugin> */
    use Composition;

    /**
     * Generates complete InstallPlugin values for the post-analysis execution boundary.
     *
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Plugin\InstallPlugin>|null $operands A constrained form of this concrete result
     * @param ErisGenerator<\SqlSemantics\Contract\AnalysisContext>|null $contexts Related declarations and session settings
     */
    public function __construct(\SqlSemantics\Contract\GrammarRelease $release, ?ErisGenerator $operands = null, ?ErisGenerator $contexts = null)
    {
        if ($operands === null) {
            $plugin = \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(Generators::oneOf(Generators::elements('InnoDB', 'innodb', 'MEMORY', 'mysqlx', 'mysql_native_password'), Generators::map(static fn (int $id): string => 'plugin_' . $id, Generators::choose(0, 32))), Generators::constant(null));
            $library = new \Tests\Generator\MySql\Statement\Literal\TextGenerator(Generators::oneOf(Generators::elements('', '.', '..', '/missing.so', 'missing/part'), Generators::map(static fn (int $length): string => str_repeat('x', $length), Generators::choose(1, 70))));
            $forms = [self::operands($plugin, $library)];
            if ($release === \SqlSemantics\Contract\GrammarRelease::MySql847) {
                foreach (self::forms() as $form) {
                    $forms[] = $form;
                }
            }

            $operands = Generators::oneOf(...$forms);
        }
        $this->delegate = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): \SqlSemantics\Platform\MySql\Statement\Server\Plugin\InstallPlugin {
            if (!$statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Plugin\InstallPlugin) {
                throw new LogicException('Publishing InstallPlugin must preserve its concrete result type.');
            }
            return $statement;
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $operands, $contexts));
    }
    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $plugin Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Text> $library Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Plugin\InstallPlugin>
     */
    public static function operands(ErisGenerator $plugin, ErisGenerator $library, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null): ErisGenerator
    {
        return Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Plugin\InstallPlugin {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Plugin\InstallPlugin(plugin: $parts[0], library: $parts[1], statementInformation: $parts[2], inputSource: $parts[3]);
        }, Generators::tuple($plugin, $library, $statementInformation ?? Generators::constant(null), $inputSource ?? Generators::constant(null)));
    }
    /**
     * Composes the statement's MySQL 8.4 forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Plugin\InstallPlugin>>
     */
    public static function forms(): iterable
    {
        yield 'InstallPlugin/421' => InstallPluginGenerator::operands(
            plugin: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                quote: Generators::constant(null)
            ),
            library: new \Tests\Generator\MySql\Statement\Literal\TextGenerator(
                value: Generators::constant('text'),
                radix: Generators::constant(null)
            )
        );
    }

}
