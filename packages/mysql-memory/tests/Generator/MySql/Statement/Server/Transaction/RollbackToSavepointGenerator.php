<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Transaction;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use LogicException;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\RollbackToSavepoint;

/**
 * Composes RollbackToSavepoint operands through Eris; shrinking reconstructs the same SQL alternative.
 *
 * @implements ErisGenerator<RollbackToSavepoint>
 */
final class RollbackToSavepointGenerator implements ErisGenerator
{
    /** @var ErisGenerator<RollbackToSavepoint> */
    private ErisGenerator $delegate;

    /**
     * Selects the grammar before generating operands.
     */
    public function __construct(GrammarRelease $release)
    {
        $names = Generators::oneOf(new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(quotes: Generators::constant(\SqlSemantics\Statement\Identifier\NameQuote::Backtick)), Generators::map(static fn (string $value): \SqlSemantics\Statement\Identifier\Name => new \SqlSemantics\Statement\Identifier\Name($value, \SqlSemantics\Statement\Identifier\NameQuote::Bare), Generators::elements('FULL', 'full', 'fUlL')));
        $this->delegate = Generators::map(static function (array $parts) use ($release): RollbackToSavepoint {
            [$name, $work, $savepointWritten] = $parts;
            if (!$name instanceof \SqlSemantics\Statement\Identifier\Name || !is_bool($work) || !is_bool($savepointWritten)) {
                throw new LogicException('A rollback names a savepoint and its optional words.');
            }
            return new RollbackToSavepoint($name, $work, $savepointWritten, profile: new LanguageProfile($release));
        }, Generators::tuple($names, Generators::bool(), Generators::bool()));
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValueSingle<RollbackToSavepoint>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<RollbackToSavepoint>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
