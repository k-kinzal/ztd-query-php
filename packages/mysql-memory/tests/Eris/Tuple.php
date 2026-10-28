<?php

declare(strict_types=1);

namespace Tests\Eris;

use Eris\Generator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueOptions;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generator\TupleGenerator;
use LogicException;
use Override;

/**
 * Shrinks one composed constructor operand at a time through its original Eris generator.
 *
 * @template T
 * @extends TupleGenerator<mixed>
 */
final class Tuple extends TupleGenerator
{
    /**
     * @param list<Generator<T>> $operands
     */
    public function __construct(private readonly array $operands)
    {
        parent::__construct($operands);
    }

    /**
     * @param GeneratedValue<mixed> $tuple
     * @return GeneratedValue<list<mixed>>
     * @throws LogicException When the input was not produced by this tuple
     */
    #[Override]
    public function shrink(GeneratedValue $tuple): GeneratedValue
    {
        $input = $tuple->input();
        $original = $tuple->unbox();
        if (!is_array($input) || !array_is_list($input) || count($input) !== count($this->operands)) {
            throw new LogicException('Constructor operands require a matching Eris tuple input.');
        }
        if (!is_array($original) || !array_is_list($original) || count($original) !== count($input)) {
            throw new LogicException('Constructor operands require a matching Eris tuple value.');
        }
        foreach ($input as $value) {
            if (!$value instanceof GeneratedValue) {
                throw new LogicException('Constructor operands retain their Eris generation inputs.');
            }
        }
        $options = [];
        foreach ($this->operands as $index => $generator) {
            foreach ($generator->shrink($input[$index]) as $candidate) {
                if ((new GeneratedValueOptions([$candidate]))->remove($input[$index])->count() === 0) {
                    continue;
                }
                $values = $original;
                $values[$index] = $candidate->unbox();
                $inputs = $input;
                $inputs[$index] = $candidate;
                $options[] = GeneratedValueSingle::fromValueAndInput(array_values($values), $inputs, 'tuple');
            }
        }
        /** Eris's frequency generator unboxes even an irreducible result. */
        return $options === [] ? GeneratedValueSingle::fromValueAndInput($original, $input, 'tuple') : new GeneratedValueOptions($options);
    }
}
