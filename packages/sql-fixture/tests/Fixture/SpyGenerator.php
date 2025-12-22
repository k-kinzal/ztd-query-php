<?php

declare(strict_types=1);

namespace Tests\Fixture;

use Faker\Generator;
use Faker\Provider\Miscellaneous;

/**
 * Spy subclass of Faker\Generator that records arguments to number/float/boolean methods.
 *
 * Faker\Generator defines numberBetween() and randomFloat() as real instance methods
 * (not via __call/providers), so a provider-based spy cannot intercept them.
 * This subclass overrides them directly to record arguments before delegating.
 *
 * Method signatures must match Faker\Generator exactly (untyped params).
 */
final class SpyGenerator extends Generator
{
    /** @var list<array{mixed, mixed}> */
    public array $numberBetweenCalls = [];

    /** @var list<array{mixed, mixed, mixed}> */
    public array $randomFloatCalls = [];

    /** @var list<array{int}> */
    public array $booleanCalls = [];

    /** @var array<string, list<array<mixed>>> */
    public array $methodCalls = [];

    public static function create(string $locale = 'en_US'): self
    {
        $spy = new self();
        $defaultProviders = ['Address', 'Barcode', 'Biased', 'Color', 'Company', 'DateTime', 'File', 'HtmlLorem', 'Image', 'Internet', 'Lorem', 'Medical', 'Miscellaneous', 'Payment', 'Person', 'PhoneNumber', 'Text', 'UserAgent', 'Uuid'];

        foreach ($defaultProviders as $provider) {
            $localeClass = "Faker\\Provider\\{$locale}\\{$provider}";
            $baseClass = "Faker\\Provider\\{$provider}";

            if (class_exists($localeClass, true)) {
                $spy->addProvider(new $localeClass($spy));
            } elseif (class_exists($baseClass, true)) {
                $spy->addProvider(new $baseClass($spy));
            }
        }

        return $spy;
    }

    public function reset(): void
    {
        $this->numberBetweenCalls = [];
        $this->randomFloatCalls = [];
        $this->booleanCalls = [];
        $this->methodCalls = [];
    }

    /**
     * @param array<mixed> $attributes
     * @return mixed
     */
    public function __call($method, $attributes)
    {
        $this->methodCalls[$method][] = $attributes;

        return parent::__call($method, $attributes);
    }

    /**
     * @param mixed $int1
     * @param mixed $int2
     */
    public function numberBetween($int1 = 0, $int2 = 2147483647): int
    {
        $this->numberBetweenCalls[] = [$int1, $int2];

        return parent::numberBetween($int1, $int2);
    }

    /**
     * @param mixed $nbMaxDecimals
     * @param mixed $min
     * @param mixed $max
     */
    public function randomFloat($nbMaxDecimals = null, $min = 0, $max = null): float
    {
        $this->randomFloatCalls[] = [$nbMaxDecimals, $min, $max];

        return parent::randomFloat($nbMaxDecimals, $min, $max);
    }

    /**
     * @param int $chanceOfGettingTrue
     */
    public function boolean($chanceOfGettingTrue = 50): bool
    {
        $this->booleanCalls[] = [$chanceOfGettingTrue];

        return Miscellaneous::boolean($chanceOfGettingTrue);
    }
}
