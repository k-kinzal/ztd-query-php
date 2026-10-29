<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Transaction\Xa;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use InvalidArgumentException;
use LogicException;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaRecover;

/**
 * Composes XaRecover operands through Eris; shrinking reconstructs the same SQL alternative.
 *
 * @implements ErisGenerator<XaRecover>
 */
final class XaRecoverGenerator implements ErisGenerator
{
    /** @var ErisGenerator<XaRecover> */
    private ErisGenerator $delegate;

    /**
     * Generates complete XaRecover values for the post-analysis execution boundary.
     *
     * @param ErisGenerator<XaRecover>|null $operands A constrained form of this concrete result
     * @param ErisGenerator<\SqlSemantics\Contract\AnalysisContext>|null $contexts Related declarations and session settings
     * @throws InvalidArgumentException When no form is available for the release.
     */
    public function __construct(GrammarRelease $release, ?ErisGenerator $operands = null, ?ErisGenerator $contexts = null)
    {
        if ($operands === null) {
            $forms = [];
            if ($release === GrammarRelease::MySql847) {
                foreach (self::forms() as $form) {
                    $forms[] = $form;
                }
            }

            if ($forms === []) {
                throw new InvalidArgumentException('No XaRecover forms are defined for the selected grammar.');
            }
            $operands = Generators::oneOf(...$forms);
        }
        $this->delegate = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): XaRecover {
            if (!$statement instanceof XaRecover) {
                throw new LogicException('Publishing XaRecover must preserve its concrete result type.');
            }
            return $statement;
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $operands, $contexts));
    }
    /**
     * Selects the grammar before generating operands.
     * @return ErisGenerator<XaRecover>
     */
    public static function operands(GrammarRelease $release): ErisGenerator
    {
        return Generators::map(static fn (bool $convert): XaRecover => new XaRecover($convert, profile: new LanguageProfile($release)), $release === GrammarRelease::MySql5651 ? Generators::constant(false) : Generators::bool());
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValueSingle<XaRecover>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<XaRecover>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
    /**
     * Composes all semantic operands, including spellings outside the convenience defaults.
     *
     * @param ErisGenerator<bool> $convertXid The generated semantic operand
     * @param ErisGenerator<LanguageProfile> $profile The generated semantic operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset|null> $client The generated semantic operand
     * @return ErisGenerator<XaRecover>
     */
    public static function fromOperands(ErisGenerator $convertXid, ErisGenerator $profile, ErisGenerator $client): ErisGenerator
    {
        return Generators::map(static fn (array $parts): XaRecover => new XaRecover(convertXid: $parts[0], profile: $parts[1], client: $parts[2]), Generators::tuple($convertXid, $profile, $client));
    }

    /**
     * Composes the statement's MySQL 8.4 forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<XaRecover>>
     */
    public static function forms(): iterable
    {
        yield 'XaRecover/130' => XaRecoverGenerator::fromOperands(
            convertXid: Generators::constant(false),
            profile: new \Tests\Generator\Semantics\Contract\LanguageProfileGenerator(
                grammar: \Tests\Generator\Semantics\Contract\GrammarReleaseGenerator::selecting([GrammarRelease::MySql847]),
                lexical: new \Tests\Generator\Semantics\Contract\LexicalSettingsGenerator(
                    settings: Generators::constant('')
                ),
                parameters: \Tests\Generator\Semantics\Contract\ParameterStyleGenerator::selecting([\SqlSemantics\Contract\ParameterStyle::Native])
            ),
            client: Generators::constant(null)
        );
    }

}
