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
}
