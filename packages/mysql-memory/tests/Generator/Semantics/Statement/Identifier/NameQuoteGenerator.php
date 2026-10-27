<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Identifier;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Statement\Identifier\NameQuote;

/**
 * Selects an identifier delimiter through Eris.
 *
 * @implements ErisGenerator<NameQuote>
 */
final class NameQuoteGenerator implements ErisGenerator
{
    /**
     * @var ErisGenerator<NameQuote>
     */
    private ErisGenerator $delegate;

    /**
     * Chooses among the delimiter forms allowed by the consuming language profile.
     */
    public function __construct(NameQuote ...$allowed)
    {
        $this->delegate = Generators::elements($allowed === [] ? NameQuote::cases() : $allowed);
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValueSingle<NameQuote>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<NameQuote>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
    /**
     * Selects the alternatives valid in a particular SQL role.
     *
     * @param non-empty-list<NameQuote> $cases The alternatives allowed by the parent structure
     * @return ErisGenerator<NameQuote>
     */
    public static function selecting(array $cases): ErisGenerator
    {
        return Generators::elements($cases);
    }

}
