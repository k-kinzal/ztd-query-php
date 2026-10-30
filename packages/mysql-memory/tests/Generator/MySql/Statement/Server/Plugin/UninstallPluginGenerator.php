<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Plugin;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use LogicException;
use Tests\Generator\Composition;

/**
 * Composes UninstallPlugin values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Plugin\UninstallPlugin>
 */
final class UninstallPluginGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Server\Plugin\UninstallPlugin> */
    use Composition;

    /**
     * Generates complete UninstallPlugin values for the post-analysis execution boundary.
     *
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Plugin\UninstallPlugin>|null $operands A constrained form of this concrete result
     * @param ErisGenerator<\SqlSemantics\Contract\AnalysisContext>|null $contexts Related declarations and session settings
     */
    public function __construct(\SqlSemantics\Contract\GrammarRelease $release, ?ErisGenerator $operands = null, ?ErisGenerator $contexts = null)
    {
        if ($operands === null) {
            $plugin = \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(Generators::oneOf(Generators::elements('InnoDB', 'innodb', 'MEMORY', 'mysqlx', 'mysql_native_password'), Generators::map(static fn (int $id): string => 'plugin_' . $id, Generators::choose(0, 32))), Generators::constant(null));
            $forms = [self::operands($plugin)];
            if ($release === \SqlSemantics\Contract\GrammarRelease::MySql847) {
                foreach (self::forms() as $form) {
                    $forms[] = $form;
                }
            }

            $operands = Generators::oneOf(...$forms);
        }
        $this->delegate = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): \SqlSemantics\Platform\MySql\Statement\Server\Plugin\UninstallPlugin {
            if (!$statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Plugin\UninstallPlugin) {
                throw new LogicException('Publishing UninstallPlugin must preserve its concrete result type.');
            }
            return $statement;
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $operands, $contexts));
    }
    /**
     * @param ErisGenerator<\SqlSemantics\Statement\Identifier\Name> $plugin Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Plugin\UninstallPlugin>
     */
    public static function operands(ErisGenerator $plugin, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null): ErisGenerator
    {
        return Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Plugin\UninstallPlugin {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Plugin\UninstallPlugin(plugin: $parts[0], statementInformation: $parts[1], inputSource: $parts[2]);
        }, Generators::tuple($plugin, $statementInformation ?? Generators::constant(null), $inputSource ?? Generators::constant(null)));
    }
    /**
     * Composes the statement's MySQL 8.4 forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Plugin\UninstallPlugin>>
     */
    public static function forms(): iterable
    {
        yield 'UninstallPlugin/126' => UninstallPluginGenerator::operands(
            plugin: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                quote: Generators::constant(null)
            )
        );
    }

}
