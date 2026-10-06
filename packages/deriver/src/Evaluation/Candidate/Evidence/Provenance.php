<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Candidate\Evidence;

use Deriver\Reference\SourceRef;
use Deriver\Result\Evidence\Node;
use Deriver\Value\Term;

/**
 * Keeps value identity separate from the immutable graph supporting that value.
 * @visibility root
 */
final class Provenance
{
    /**
     * @param array<string, scalar|null> $attributes
     */
    public static function wrap(Term $value, string $kind, ?SourceRef $source = null, array $attributes = []): Term
    {
        if ($value->kind === 'choice') {
            $items = [];
            foreach ($value->operands as $key => $item) {
                $items[$key] = new Term('alternative', operands: [self::wrap($item->operands[0], $kind, $source, $attributes)], attributes: $item->attributes);
            }
            return new Term('choice', operands: $items);
        }
        $inputs = $value->evidence === null ? self::inputs($value->operands) : ['value' => $value->evidence];
        return self::attach($value, new Node($kind, $inputs, $source, $attributes));
    }

    /**
     * Attaches derivation evidence without changing semantic value identity.
     */
    public static function attach(Term $value, Node $evidence): Term
    {
        return new Term($value->kind, $value->literal, $value->operands, $value->attributes, $value->secret, $evidence);
    }

    /**

     * @param array<int|string, Term> $values

     * @return array<string, Node>

     */
    public static function inputs(array $values): array
    {
        $inputs = [];
        foreach ($values as $key => $value) {
            $inputs['operand:' . $key] = $value->evidence ?? (new Forest())->root($value);
        }
        return $inputs;
    }

    /**

     * @param list<Term> $values

     */
    public static function operation(Term $result, array $values, string $operation): Term
    {
        if ($result->kind === 'choice') {
            $items = [];
            foreach ($result->operands as $key => $item) {
                $items[$key] = new Term('alternative', operands: [self::operation($item->operands[0], $values, $operation)], attributes: $item->attributes);
            }
            return new Term('choice', operands: $items);
        }
        $inputs = self::inputs($values);
        if ($result->evidence !== null) {
            $inputs['result'] = $result->evidence;
        }
        return self::attach($result, new Node('operation', $inputs, attributes: ['operation' => $operation]));
    }
    /**
     * Combines selected model metadata and demanded selection inputs with an output proof.
     */
    public static function model(Term $value, ?Node $model): Term
    {
        if ($model === null) {
            return $value;
        }
        if ($value->kind === 'choice') {
            $items = [];
            foreach ($value->operands as $key => $item) {
                $items[$key] = new Term('alternative', operands: [self::model($item->operands[0], $model)], attributes: $item->attributes);
            }
            return new Term('choice', operands: $items);
        }
        return self::attach($value, new Node('model-application', [...$model->inputs, 'output' => (new Forest())->root($value)], $model->source, $model->attributes));
    }

}
