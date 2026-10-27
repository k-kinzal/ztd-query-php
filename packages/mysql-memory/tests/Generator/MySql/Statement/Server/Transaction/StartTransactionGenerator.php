<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Transaction;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\StartTransaction;

/**
 * Composes StartTransaction operands through Eris; shrinking reconstructs the same SQL alternative.
 *
 * @implements ErisGenerator<StartTransaction>
 */
final class StartTransactionGenerator implements ErisGenerator
{
    /** @var ErisGenerator<StartTransaction> */
    private ErisGenerator $delegate;

    /**
     * Selects the grammar before generating operands.
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(static fn (array $characteristics): StartTransaction => new StartTransaction($characteristics, profile: new LanguageProfile($release)), Generators::oneOf(
            Generators::seq(Generators::elements(\SqlSemantics\Platform\MySql\Statement\Server\Transaction\TransactionCharacteristic::WithConsistentSnapshot, \SqlSemantics\Platform\MySql\Statement\Server\Transaction\TransactionCharacteristic::ReadOnly)),
            Generators::seq(Generators::elements(\SqlSemantics\Platform\MySql\Statement\Server\Transaction\TransactionCharacteristic::WithConsistentSnapshot, \SqlSemantics\Platform\MySql\Statement\Server\Transaction\TransactionCharacteristic::ReadWrite)),
        ));
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValueSingle<StartTransaction>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<StartTransaction>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
    /**
     * Composes all semantic operands, including spellings outside the convenience defaults.
     *
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Server\Transaction\TransactionCharacteristic>> $characteristics The generated semantic operand
     * @param ErisGenerator<LanguageProfile> $profile The generated semantic operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset|null> $client The generated semantic operand
     * @return ErisGenerator<StartTransaction>
     */
    public static function fromOperands(ErisGenerator $characteristics, ErisGenerator $profile, ErisGenerator $client): ErisGenerator
    {
        return Generators::map(static fn (array $parts): StartTransaction => new StartTransaction(characteristics: $parts[0], profile: $parts[1], client: $parts[2]), \Tests\Generator\Operands::tuple($characteristics, $profile, $client));
    }

    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<StartTransaction>>
     */
    public static function forms(): iterable
    {
        yield 'StartTransaction/122' => StartTransactionGenerator::fromOperands(
            characteristics: Generators::constant([]),
            profile: new \Tests\Generator\Semantics\Contract\LanguageProfileGenerator(
                grammar: \Tests\Generator\Semantics\Contract\GrammarReleaseGenerator::selecting([GrammarRelease::MySql847]),
                lexical: new \Tests\Generator\Semantics\Contract\LexicalSettingsGenerator(
                    settings: Generators::constant('')
                ),
                parameters: \Tests\Generator\Semantics\Contract\ParameterStyleGenerator::selecting([\SqlSemantics\Contract\ParameterStyle::Native])
            ),
            client: Generators::constant(null)
        );
        yield 'StartTransaction/280' => StartTransactionGenerator::fromOperands(
            characteristics: \Tests\Generator\Operands::tuple(
                TransactionCharacteristicGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Server\Transaction\TransactionCharacteristic::ReadOnly])
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
