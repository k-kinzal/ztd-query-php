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
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaEnd;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaEndOption;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\Xid;

/**
 * Composes XaEnd operands through Eris; shrinking reconstructs the same SQL alternative.
 *
 * @implements ErisGenerator<XaEnd>
 */
final class XaEndGenerator implements ErisGenerator
{
    /** @var ErisGenerator<XaEnd> */
    private ErisGenerator $delegate;

    /**
     * Selects the grammar before generating operands.
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(static function (array $data) use ($release): XaEnd {
            [$xid, $option] = $data;
            return new XaEnd($xid, $option, profile: new LanguageProfile($release));
        }, \Tests\Generator\Operands::tuple(new XidGenerator(), Generators::elements(null, XaEndOption::Suspend, XaEndOption::SuspendForMigrate)));
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValueSingle<XaEnd>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<XaEnd>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
    /**
     * Composes all semantic operands, including spellings outside the convenience defaults.
     *
     * @param ErisGenerator<Xid> $xid The generated semantic operand
     * @param ErisGenerator<XaEndOption|null> $option The generated semantic operand
     * @param ErisGenerator<LanguageProfile> $profile The generated semantic operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset|null> $client The generated semantic operand
     * @return ErisGenerator<XaEnd>
     */
    public static function fromOperands(ErisGenerator $xid, ErisGenerator $option, ErisGenerator $profile, ErisGenerator $client): ErisGenerator
    {
        return Generators::map(static fn (array $parts): XaEnd => new XaEnd(xid: $parts[0], option: $parts[1], profile: $parts[2], client: $parts[3]), \Tests\Generator\Operands::tuple($xid, $option, $profile, $client));
    }

    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<XaEnd>>
     */
    public static function forms(): iterable
    {
        yield 'XaEnd/414' => XaEndGenerator::fromOperands(
            xid: XidGenerator::fromOperands(
                transaction: new \Tests\Generator\MySql\Statement\Literal\TextGenerator(
                    value: Generators::constant('0f'),
                    radix: \Tests\Generator\MySql\Statement\Literal\RadixGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Literal\Radix::Hexadecimal])
                ),
                branch: Generators::constant(null),
                format: Generators::constant(null)
            ),
            option: Generators::constant(null),
            profile: new \Tests\Generator\Semantics\Contract\LanguageProfileGenerator(
                grammar: \Tests\Generator\Semantics\Contract\GrammarReleaseGenerator::selecting([GrammarRelease::MySql847]),
                lexical: new \Tests\Generator\Semantics\Contract\LexicalSettingsGenerator(
                    settings: Generators::constant('')
                ),
                parameters: \Tests\Generator\Semantics\Contract\ParameterStyleGenerator::selecting([\SqlSemantics\Contract\ParameterStyle::Native])
            ),
            client: Generators::constant(null)
        );
        yield 'XaEnd/419' => XaEndGenerator::fromOperands(
            xid: XidGenerator::fromOperands(
                transaction: new \Tests\Generator\MySql\Statement\Literal\TextGenerator(
                    value: Generators::constant('0f'),
                    radix: \Tests\Generator\MySql\Statement\Literal\RadixGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Literal\Radix::Hexadecimal])
                ),
                branch: Generators::constant(null),
                format: Generators::constant(null)
            ),
            option: XaEndOptionGenerator::selecting([XaEndOption::Suspend]),
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
