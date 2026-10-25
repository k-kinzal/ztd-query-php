<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement\Source;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Statement\Source\Span;

/**
 * Constructs bounded source metadata without retaining input text.
 *
 * @implements ErisGenerator<Span>
 */
final class SpanGenerator implements ErisGenerator
{
    /**
     * @var ErisGenerator<Span>
     */
    private ErisGenerator $delegate;

    /**
     * Uses Eris's size-bounded endpoints and shrinks them toward zero.
     * Ordering the endpoints also prevents the range end from overflowing.
     */
    public function __construct()
    {
        $this->delegate = Generators::map(static fn (array $data): Span => new Span(min($data['start'], $data['end']), max($data['start'], $data['end']) - min($data['start'], $data['end'])), Generators::associative(['start' => Generators::nat(), 'end' => Generators::nat()]));
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValueSingle<Span>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<Span>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
