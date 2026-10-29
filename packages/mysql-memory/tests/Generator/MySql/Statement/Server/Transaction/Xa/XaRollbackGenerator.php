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
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaRollback;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\Xid;

/**
 * Composes XaRollback operands through Eris; shrinking reconstructs the same SQL alternative.
 *
 * @implements ErisGenerator<XaRollback>
 */
final class XaRollbackGenerator implements ErisGenerator
{
    /** @var ErisGenerator<XaRollback> */
    private ErisGenerator $delegate;

    /**
     * Generates complete XaRollback values for the post-analysis execution boundary.
     *
     * @param ErisGenerator<XaRollback>|null $operands A constrained form of this concrete result
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
                throw new InvalidArgumentException('No XaRollback forms are defined for the selected grammar.');
            }
            $operands = Generators::oneOf(...$forms);
        }
        $this->delegate = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): XaRollback {
            if (!$statement instanceof XaRollback) {
                throw new LogicException('Publishing XaRollback must preserve its concrete result type.');
            }
            return $statement;
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $operands, $contexts));
    }
    /**
     * Selects the grammar before generating operands.
     * @return ErisGenerator<XaRollback>
     */
    public static function operands(GrammarRelease $release): ErisGenerator
    {
        return Generators::map(static fn (Xid $xid): XaRollback => new XaRollback($xid, profile: new LanguageProfile($release)), new XidGenerator());
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValueSingle<XaRollback>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<XaRollback>
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
     * @return ErisGenerator<XaRollback>
     */
    public static function fromOperands(ErisGenerator $xid, ErisGenerator $profile, ErisGenerator $client): ErisGenerator
    {
        return Generators::map(static fn (array $parts): XaRollback => new XaRollback(xid: $parts[0], profile: $parts[1], client: $parts[2]), Generators::tuple($xid, $profile, $client));
    }

    /**
     * Composes the statement's MySQL 8.4 forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<XaRollback>>
     */
    public static function forms(): iterable
    {
        yield 'XaRollback/417' => XaRollbackGenerator::fromOperands(
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
