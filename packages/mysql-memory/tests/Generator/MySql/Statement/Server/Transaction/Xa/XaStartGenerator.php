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
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaStart;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaStartOption;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\Xid;

/**
 * Composes XaStart operands through Eris; shrinking reconstructs the same SQL alternative.
 *
 * @implements ErisGenerator<XaStart>
 */
final class XaStartGenerator implements ErisGenerator
{
    /** @var ErisGenerator<XaStart> */
    private ErisGenerator $delegate;

    /**
     * Generates complete XaStart values for the post-analysis execution boundary.
     *
     * @param ErisGenerator<XaStart>|null $operands A constrained form of this concrete result
     * @param ErisGenerator<\SqlSemantics\Contract\AnalysisContext>|null $contexts Related declarations and session settings
     * @throws InvalidArgumentException When the release belongs to another database.
     */
    public function __construct(GrammarRelease $release, ?ErisGenerator $operands = null, ?ErisGenerator $contexts = null)
    {
        if ($release->database() !== 'mysql') {
            throw new InvalidArgumentException('XA statements require a MySQL grammar release.');
        }
        if ($operands === null) {
            $forms = [self::operands($release)];
            if ($release === GrammarRelease::MySql847) {
                foreach (self::forms() as $form) {
                    $forms[] = $form;
                }
            }

            $operands = Generators::oneOf(...$forms);
        }
        $this->delegate = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): XaStart {
            if (!$statement instanceof XaStart) {
                throw new LogicException('Publishing XaStart must preserve its concrete result type.');
            }
            return $statement;
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $operands, $contexts));
    }
    /**
     * Selects the grammar before generating operands.
     * @return ErisGenerator<XaStart>
     */
    public static function operands(GrammarRelease $release): ErisGenerator
    {
        return Generators::map(static function (array $data) use ($release): XaStart {
            [$xid, $option, $begin] = $data;
            return new XaStart($xid, $option, profile: new LanguageProfile($release), begin: $begin);
        }, Generators::tuple(new XidGenerator(), Generators::elements(null, XaStartOption::Join, XaStartOption::Resume), Generators::bool()));
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValueSingle<XaStart>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<XaStart>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
    /**
     * Composes all semantic operands, including spellings outside the convenience defaults.
     *
     * @param ErisGenerator<Xid> $xid The generated semantic operand
     * @param ErisGenerator<XaStartOption|null> $option The generated semantic operand
     * @param ErisGenerator<LanguageProfile> $profile The generated semantic operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset|null> $client The generated semantic operand
     * @param ErisGenerator<bool>|null $begin The generated START or BEGIN form
     * @return ErisGenerator<XaStart>
     */
    public static function fromOperands(ErisGenerator $xid, ErisGenerator $option, ErisGenerator $profile, ErisGenerator $client, ?ErisGenerator $begin = null): ErisGenerator
    {
        return Generators::map(static fn (array $parts): XaStart => new XaStart(xid: $parts[0], option: $parts[1], profile: $parts[2], client: $parts[3], begin: $parts[4]), Generators::tuple($xid, $option, $profile, $client, $begin ?? Generators::bool()));
    }

    /**
     * Composes the statement's MySQL 8.4 forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<XaStart>>
     */
    public static function forms(): iterable
    {
        yield 'XaStart/413' => XaStartGenerator::fromOperands(
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
        yield 'XaStart/418' => XaStartGenerator::fromOperands(
            xid: XidGenerator::fromOperands(
                transaction: new \Tests\Generator\MySql\Statement\Literal\TextGenerator(
                    value: Generators::constant('0f'),
                    radix: \Tests\Generator\MySql\Statement\Literal\RadixGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Literal\Radix::Hexadecimal])
                ),
                branch: Generators::constant(null),
                format: Generators::constant(null)
            ),
            option: XaStartOptionGenerator::selecting([XaStartOption::Join]),
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
