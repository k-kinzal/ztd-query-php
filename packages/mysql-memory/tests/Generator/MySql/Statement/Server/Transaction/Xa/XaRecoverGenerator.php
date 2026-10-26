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
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Xa\XaRecover;

/**
 * Composes XaRecover operands through Eris; shrinking reconstructs the same SQL alternative.
 *
 * @implements ErisGenerator<XaRecover>
 */
final class XaRecoverGenerator implements ErisGenerator
{
    /** @var ErisGenerator<XaRecover> */
    private ErisGenerator $delegate;

    /**
     * Selects the grammar before generating operands.
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(static fn (bool $convert): XaRecover => new XaRecover($convert, profile: new LanguageProfile($release)), $release === GrammarRelease::MySql5651 ? Generators::constant(false) : Generators::bool());
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValueSingle<XaRecover>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<XaRecover>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
