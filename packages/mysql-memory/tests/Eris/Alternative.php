<?php

declare(strict_types=1);

namespace Tests\Eris;

use Eris\Generator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use LogicException;
use Override;
use Traversable;

/**
 * Retains every nested Eris shrink candidate of the selected semantic alternative.
 *
 * @template U
 * @extends \Eris\Generator\OneOfGenerator<U>
 */
final class Alternative extends Generator\OneOfGenerator
{
    /**
     * @param non-empty-list<Generator<U>> $alternatives
     */
    public function __construct(private readonly array $alternatives)
    {
        parent::__construct($alternatives);
    }

    /**
     * @param GeneratedValue<mixed> $element
     * @return GeneratedValue<U>
     * @throws LogicException When the value is outside the Eris alternative protocol
     */
    #[Override]
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        $input = $element->input();
        if (!is_array($input) || !isset($input['generator'], $input['value']) || !is_int($input['generator']) || !isset($this->alternatives[$input['generator']]) || !$input['value'] instanceof GeneratedValue) {
            throw new LogicException('An alternative retains the Eris choice and its generation input.');
        }
        $index = $input['generator'];
        return new Options(function () use ($input, $index): Traversable {
            foreach ($this->alternatives[$index]->shrink($input['value']) as $candidate) {
                yield GeneratedValueSingle::fromValueAndInput($candidate->unbox(), ['value' => $candidate, 'generator' => $index], 'frequency');
            }
        });
    }
}
