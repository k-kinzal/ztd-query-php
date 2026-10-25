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
     * Independently shrinks the range start and width.
     */
    public function __construct()
    {
        $this->delegate = Generators::map(static fn (array $data): Span => new Span($data['offset'], $data['length']), Generators::associative(['offset' => Generators::choose(0, 1000), 'length' => Generators::choose(0, 1000)]));
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
