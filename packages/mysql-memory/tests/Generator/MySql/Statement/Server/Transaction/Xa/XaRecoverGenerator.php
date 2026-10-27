<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Transaction\Xa;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
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
     * Selects the grammar before generating operands.
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(static fn (bool $convert): XaRecover => new XaRecover($convert, profile: new LanguageProfile($release)), $release === GrammarRelease::MySql5651 ? Generators::constant(false) : Generators::bool());
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
        return Generators::map(static fn (array $parts): XaRecover => new XaRecover(convertXid: $parts[0], profile: $parts[1], client: $parts[2]), \Tests\Generator\Operands::tuple($convertXid, $profile, $client));
    }

    /**
     * Composes the statement's SQL forms from typed operand generators.
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
