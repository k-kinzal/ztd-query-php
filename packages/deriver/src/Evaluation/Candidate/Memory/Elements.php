<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Candidate\Memory;

use Deriver\Evaluation\Candidate\Derivation;
use Deriver\Value\Term;

/**
 * Reconstructs nested updates without discarding unaffected array entries.
 * @visibility root
 */
final class Elements
{
    /**
     * Reuses ordinary array projection and update rules.
     */
    public function __construct(private readonly Derivation $engine)
    {
    }

    /**

     * @param non-empty-list<Term> $path

     */
    public function write(Term $array, array $path, Term $value): Term
    {
        $key = array_shift($path);
        if ($path !== []) {
            $child = $this->engine->operation('array-read', '', [$array, $key]);
            $value = $this->write($child, $path, $value);
        }
        return $this->engine->operation('array-set', '', [$array, $key, $value]);
    }
    /**
     * Applies the leaf mutation and rebuilds only its containing arrays.
     * @param non-empty-list<Term> $path Nested element keys
     */
    public function mutate(Term $array, array $path, \Deriver\ControlFlow\Instruction $write, Term $right): Term
    {
        $key = array_shift($path);
        $before = $this->engine->element($array, $key);
        if ($path !== []) {
            return $this->engine->operation('array-set', '', [$array, $key, $this->mutate($before, $path, $write, $right)]);
        }
        if ($write->operation === 'unset') {
            return (new \Deriver\Evaluation\Candidate\Choices())->apply('array-unset', [$array, $key], static function (array $values): Term {
                [$array, $key] = $values;
                if ($array->kind !== 'array' || $key->kind !== 'constant') {
                    return new Term('array-unset', operands: $values, attributes: ['reason' => 'UNRESOLVED_ELEMENT']);
                }
                $entries = $array->operands;
                unset($entries[(string) $key->literal]);
                return new Term('array', operands: $entries, attributes: $array->attributes);
            }, $this->engine->context->budget->partitions);
        }
        $value = match ($write->operation) {
            'increment' => Mutations::increment($this->engine, $write, $before),
            'compound' => $this->engine->operation('binary', $write->name, [$before, $right]),
            default => $right,
        };
        return $this->engine->operation('array-set', '', [$array, $key, $value]);
    }

}
