<?php

declare(strict_types=1);

namespace Deriver\Deriver\Identity;

use Deriver\Deriver\Evaluator\Scalar\Identity;
use Deriver\Deriver\Evaluator\Term;

/**
 * Canonical, acyclic value records with shared expression references.
 *
 * @phpstan-type ScalarRecord array{type: string, value: string|bool|null}
 * @phpstan-type ValueRecord array{kind: string, literal: ScalarRecord, operands: list<array{key: ScalarRecord, value: string}>, attributes: list<array{key: string, value: ScalarRecord}>}
 * @visibility namespace
 */
final class ValueGraph
{
    /**
     * One identity traversal per graph preserves shared substructure.
     */
    private Identity $identity;

    /**
     * @var array<string, ValueRecord> Canonical value graph.
     */
    public array $records = [];
    /**
     * @var array<string, string> Content identities mapped to output references.
     */
    public array $identities = [];

    /**
     * Shares memoized identities during construction of this value graph.
     */
    public function __construct()
    {
        $this->identity = new Identity();
    }

    /**
     * Interns a value and returns its shared graph reference.
     * @param Term $value Immutable abstract value
     * @return string Output graph identifier
     */
    public function add(Term $value): string
    {
        $identity = $this->identity->key($value);
        if (isset($this->identities[$identity])) {
            return $this->identities[$identity];
        }
        $id = 'v' . count($this->identities);
        $this->identities[$identity] = $id;
        $operands = [];
        $attributes = [];
        foreach ($value->operands as $key => $operand) {
            $operands[] = ['key' => $this->scalar($key), 'value' => $this->add($operand)];
        }
        foreach ($value->attributes as $key => $attribute) {
            $attributes[] = ['key' => $key, 'value' => $this->scalar($attribute)];
        }
        $this->records[$id] = ['kind' => $value->kind->value, 'literal' => $this->scalar($value->literal), 'operands' => $operands, 'attributes' => $attributes];
        return $id;
    }

    /**
     * Encodes strings as bytes, integers as decimal text, and IEEE floats as hex.
     * @param scalar|null $value Scalar payload
     * @return ScalarRecord Lossless tagged scalar
     */
    public function scalar(int|float|string|bool|null $value): array
    {
        if (is_string($value)) {
            return ['type' => 'bytes', 'value' => base64_encode($value)];
        }
        if (is_int($value)) {
            return ['type' => 'int64', 'value' => (string) $value];
        }
        if (is_float($value)) {
            return ['type' => 'float64', 'value' => bin2hex(pack('E', $value))];
        }
        return ['type' => $value === null ? 'null' : 'bool', 'value' => $value];
    }

    /**
     * Encodes a named projection map as value references.
     * @param array<string, Term> $values Named terms
     * @return array<string, string> Named graph references
     */
    public function mapping(array $values): array
    {
        $references = [];
        foreach ($values as $name => $value) {
            $references[$name] = $this->add($value);
        }
        return $references;
    }
}
