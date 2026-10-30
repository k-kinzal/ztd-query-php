<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Plugin;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use LogicException;
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
     * Generates complete UninstallComponent values for the post-analysis execution boundary.
     *
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Plugin\UninstallComponent>|null $operands A constrained form of this concrete result
     * @param ErisGenerator<\SqlSemantics\Contract\AnalysisContext>|null $contexts Related declarations and session settings
     */
    public function __construct(\SqlSemantics\Contract\GrammarRelease $release, ?ErisGenerator $operands = null, ?ErisGenerator $contexts = null)
    {
        if ($operands === null) {
            $urn = Generators::map(static fn (array $parts): string => $parts[0] . 'pbt_' . $parts[1] . $parts[2], Generators::tuple(Generators::elements('', 'file://', 'unknown://'), Generators::choose(0, 32), Generators::elements('', '.so', '/part', "\0tail")));
            $component = new \Tests\Generator\MySql\Statement\Literal\TextGenerator($urn);
            $components = Generators::bind(Generators::choose(1, 4), static fn (int $count): ErisGenerator => Generators::vector($count, $component));
            $forms = [self::operands($components)];
            if ($release === \SqlSemantics\Contract\GrammarRelease::MySql847) {
                foreach (self::forms() as $form) {
                    $forms[] = $form;
                }
            }

            $operands = Generators::oneOf(...$forms);
        }
        $this->delegate = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): \SqlSemantics\Platform\MySql\Statement\Server\Plugin\UninstallComponent {
            if (!$statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Plugin\UninstallComponent) {
                throw new LogicException('Publishing UninstallComponent must preserve its concrete result type.');
            }
            return $statement;
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $operands, $contexts));
    }
    /**
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Literal\Text>> $components Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Plugin\UninstallComponent>
     */
    public static function operands(ErisGenerator $components, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null): ErisGenerator
    {
        return Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Server\Plugin\UninstallComponent {
            return new \SqlSemantics\Platform\MySql\Statement\Server\Plugin\UninstallComponent(components: $parts[0], statementInformation: $parts[1], inputSource: $parts[2]);
        }, Generators::tuple($components, $statementInformation ?? Generators::constant(null), $inputSource ?? Generators::constant(null)));
    }
    /**
     * Composes the statement's MySQL 8.4 forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Server\Plugin\UninstallComponent>>
     */
    public static function forms(): iterable
    {
        yield 'UninstallComponent/422' => UninstallComponentGenerator::operands(
            components: Generators::tuple(
                new \Tests\Generator\MySql\Statement\Literal\TextGenerator(
                    value: Generators::constant('text'),
                    radix: Generators::constant(null)
                )
            )
        );
    }

}
