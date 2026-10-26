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
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\ReleaseSavepoint;

/**
 * Composes ReleaseSavepoint operands through Eris; shrinking reconstructs the same SQL alternative.
 *
 * @implements ErisGenerator<ReleaseSavepoint>
 */
final class ReleaseSavepointGenerator implements ErisGenerator
{
    /** @var ErisGenerator<ReleaseSavepoint> */
    private ErisGenerator $delegate;

    /**
     * Selects the grammar before generating operands.
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(static fn (\SqlSemantics\Statement\Identifier\Name $name): ReleaseSavepoint => new ReleaseSavepoint($name, profile: new LanguageProfile($release)), Generators::oneOf(new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator(quotes: Generators::constant(\SqlSemantics\Statement\Identifier\NameQuote::Backtick)), Generators::map(static fn (string $value): \SqlSemantics\Statement\Identifier\Name => new \SqlSemantics\Statement\Identifier\Name($value, \SqlSemantics\Statement\Identifier\NameQuote::Bare), Generators::elements('FULL', 'full', 'fUlL'))));
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValueSingle<ReleaseSavepoint>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<ReleaseSavepoint>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
