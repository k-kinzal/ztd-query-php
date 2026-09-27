<?php

declare(strict_types=1);

namespace Tests\Fake\Oracle;

use Deriver\Result\StorageSnapshot;
use Deriver\Value\Term;
use JsonException;

/**
 * Interprets the public storage contract without using internal evaluator or lattice operations.
 * @visibility root
 */
final class DerivedState
{
    /**
     * @var array<string, int> Encounter-order object identities
     */
    public array $objects = [];

    /**
     * @param StorageSnapshot $storage Exported immutable storage graph
     */
    public function __construct(public readonly StorageSnapshot $storage)
    {
    }

    /**
     * Encodes the same externally observable roots as the independent runtime harness.
     * @param Term $value Scalar return value, or null for an exceptional exit
     * @param string $exception Observed throwable class
     * @return string Canonical observation for this concrete generated program
     * @throws JsonException If a fixture contains an invalid JSON scalar
     */
    public function envelope(Term $value, string $exception): string
    {
        return json_encode(['value' => $value->isConcrete() ? $value->native() : ['abstract' => $value->kind], 'exception' => $exception, 'state' => $this->read($this->storage->cells['global:probe'] ?? Term::opaque('UNOBSERVED_ROOT'))->native()], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
    }

    /**
     * Follows public cell and object identities while keeping object cycles finite.
     * @param Term $term Public raw storage value
     * @param int $depth Cycle guard for malformed cell graphs
     * @return Term Canonical scalar, ordered array, object graph, or unmatched abstract marker
     */
    public function read(Term $term, int $depth = 0): Term
    {
        if ($depth > 128) {
            return Term::fromNative(['abstract' => 'cyclic-cell']);
        }
        if ($term->kind === 'cell' && is_string($term->literal)) {
            return $this->read($this->storage->cells[$term->literal], $depth + 1);
        }
        if ($term->kind === 'object' && is_string($term->literal)) {
            if (isset($this->objects[$term->literal])) {
                return Term::fromNative(['ref' => $this->objects[$term->literal]]);
            }
            $identity = count($this->objects);
            $this->objects[$term->literal] = $identity;
            return Term::array(['object' => Term::constant($identity), 'class' => Term::constant($term->attributes['class'] ?? ''), 'fields' => $this->read($this->storage->cells['object:' . $term->literal], $depth + 1)]);
        }
        if ($term->kind === 'array') {
            $entries = [];
            foreach ($term->operands as $key => $operand) {
                $entries[] = Term::array([Term::constant($key), $this->read($operand, $depth + 1)]);
            }
            return Term::array(['array' => Term::array($entries)]);
        }
        if ($term->kind !== 'constant') {
            return Term::fromNative(['abstract' => $term->kind]);
        }
        return Term::array(['scalar' => $term]);
    }
}
