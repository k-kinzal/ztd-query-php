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
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaCommit;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\Xid;

/**
 * Composes XaCommit operands through Eris; shrinking reconstructs the same SQL alternative.
 *
 * @implements ErisGenerator<XaCommit>
 */
final class XaCommitGenerator implements ErisGenerator
{
    /** @var ErisGenerator<XaCommit> */
    private ErisGenerator $delegate;

    /**
     * Selects the grammar before generating operands.
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(static function (array $data) use ($release): XaCommit {
            [$xid, $option] = $data;
            return new XaCommit($xid, $option, profile: new LanguageProfile($release));
        }, \Tests\Generator\Operands::tuple(new XidGenerator(), Generators::bool()));
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValueSingle<XaCommit>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<XaCommit>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
    /**
     * Composes all semantic operands, including spellings outside the convenience defaults.
     *
     * @param ErisGenerator<Xid> $xid The generated semantic operand
     * @param ErisGenerator<bool> $onePhase The generated semantic operand
     * @param ErisGenerator<LanguageProfile> $profile The generated semantic operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset|null> $client The generated semantic operand
     * @return ErisGenerator<XaCommit>
     */
    public static function fromOperands(ErisGenerator $xid, ErisGenerator $onePhase, ErisGenerator $profile, ErisGenerator $client): ErisGenerator
    {
        return Generators::map(static fn (array $parts): XaCommit => new XaCommit(xid: $parts[0], onePhase: $parts[1], profile: $parts[2], client: $parts[3]), \Tests\Generator\Operands::tuple($xid, $onePhase, $profile, $client));
    }

    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<XaCommit>>
     */
    public static function forms(): iterable
    {
        yield 'XaCommit/416' => XaCommitGenerator::fromOperands(
            xid: XidGenerator::fromOperands(
                transaction: new \Tests\Generator\MySql\Statement\Literal\TextGenerator(
                    value: Generators::constant('0f'),
                    radix: \Tests\Generator\MySql\Statement\Literal\RadixGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Literal\Radix::Hexadecimal])
                ),
                branch: Generators::constant(null),
                format: Generators::constant(null)
            ),
            onePhase: Generators::constant(false),
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
