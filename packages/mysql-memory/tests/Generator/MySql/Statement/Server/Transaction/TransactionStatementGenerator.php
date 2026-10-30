<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Transaction;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generators;
use Eris\Random\RandomRange;
use LogicException;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\TransactionStatement;
use Tests\Generator\MySql\Statement\Server\Transaction\Xa\XaCommitGenerator;
use Tests\Generator\MySql\Statement\Server\Transaction\Xa\XaEndGenerator;
use Tests\Generator\MySql\Statement\Server\Transaction\Xa\XaPrepareGenerator;
use Tests\Generator\MySql\Statement\Server\Transaction\Xa\XaRecoverGenerator;
use Tests\Generator\MySql\Statement\Server\Transaction\Xa\XaRollbackGenerator;
use Tests\Generator\MySql\Statement\Server\Transaction\Xa\XaStartGenerator;
use Tests\Generator\MySql\Statement\Utility\Set\SetTransactionGenerator;

/**
 * Generates TransactionStatement values from the generators of its operands.
 *
 * @implements ErisGenerator<TransactionStatement>
 */
final class TransactionStatementGenerator implements ErisGenerator
{
    /** @var ErisGenerator<TransactionStatement> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::bind(Generators::choose(0, 13), static fn (int $choice): ErisGenerator => match ($choice) {
            0 => new BeginGenerator($release),
            1 => new CommitGenerator($release),
            2 => new ReleaseSavepointGenerator($release),
            3 => new RollbackGenerator($release),
            4 => new RollbackToSavepointGenerator($release),
            5 => new SavepointGenerator($release),
            6 => new StartTransactionGenerator($release),
            7 => new XaCommitGenerator($release),
            8 => new XaEndGenerator($release),
            9 => new XaPrepareGenerator($release),
            10 => new XaRecoverGenerator($release),
            11 => new XaRollbackGenerator($release),
            12 => new XaStartGenerator($release),
            13 => new SetTransactionGenerator($release),
            default => throw new LogicException('Unreachable alternative.'),
        });
        $this->delegate = Generators::suchThat(
            static function (TransactionStatement $value) use ($release): bool {
                $domain = new ReleaseDomain();
                $transition = (new Generations())->transition($value);

                return ($domain->of($value) & $domain->bit($release)) !== 0
                    && (in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true) ? !(new ModernForms())->requires($value) : !(new LegacyForms())->requires($value))
                    && ($transition === 0 || ($transition & ($release === GrammarRelease::MySql5651 ? 1 : 2)) !== 0)
                    && ((new LexicalModes())->requirement($value) & 10) === 0;
            },
            $this->delegate
        );
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValue<TransactionStatement>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<TransactionStatement> $element The generated counterexample
     * @return GeneratedValue<TransactionStatement>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
