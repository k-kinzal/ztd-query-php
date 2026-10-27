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
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaPrepare;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\Xid;

/**
 * Composes XaPrepare operands through Eris; shrinking reconstructs the same SQL alternative.
 *
 * @implements ErisGenerator<XaPrepare>
 */
final class XaPrepareGenerator implements ErisGenerator
{
    /** @var ErisGenerator<XaPrepare> */
    private ErisGenerator $delegate;

    /**
     * Selects the grammar before generating operands.
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(static fn (Xid $xid): XaPrepare => new XaPrepare($xid, profile: new LanguageProfile($release)), new XidGenerator());
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValueSingle<XaPrepare>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<XaPrepare>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
    /**
     * Composes all semantic operands, including spellings outside the convenience defaults.
     *
     * @param ErisGenerator<Xid> $xid The generated semantic operand
     * @param ErisGenerator<LanguageProfile> $profile The generated semantic operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset|null> $client The generated semantic operand
     * @return ErisGenerator<XaPrepare>
     */
    public static function fromOperands(ErisGenerator $xid, ErisGenerator $profile, ErisGenerator $client): ErisGenerator
    {
        return Generators::map(static fn (array $parts): XaPrepare => new XaPrepare(xid: $parts[0], profile: $parts[1], client: $parts[2]), \Tests\Generator\Operands::tuple($xid, $profile, $client));
    }

    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<XaPrepare>>
     */
    public static function forms(): iterable
    {
        yield 'XaPrepare/415' => XaPrepareGenerator::fromOperands(
            xid: XidGenerator::fromOperands(
                transaction: new \Tests\Generator\MySql\Statement\Literal\TextGenerator(
                    value: Generators::constant('0f'),
                    radix: \Tests\Generator\MySql\Statement\Literal\RadixGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Literal\Radix::Hexadecimal])
                ),
                branch: Generators::constant(null),
                format: Generators::constant(null)
            ),
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
