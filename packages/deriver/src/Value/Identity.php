<?php

declare(strict_types=1);

namespace Deriver\Value;

use WeakMap;

/**
 * Computes stable expression identities with lossless scalar encodings.
 * @visibility root
 */
final class Identity
{
    /**
     * @var WeakMap<Term, string> Identities cached only while immutable terms remain alive
     */
    private WeakMap $keys;

    /**
     * Creates a query-local weak identity cache.
     */
    public function __construct()
    {
        $this->keys = new WeakMap();
    }

    /**
     * Identifies an immutable expression independently of object allocation.
     * @param Term $term Expression
     * @return string SHA-256 content identity
     */
    public function key(Term $term): string
    {
        /** @var list<array{Term, bool}> $pending */
        $pending = [[$term, false]];
        while ($pending !== []) {
            [$current, $ready] = array_pop($pending);
            if (isset($this->keys[$current])) {
                continue;
            }
            if (!$ready) {
                $pending[] = [$current, true];
                foreach ($current->operands as $child) {
                    $pending[] = [$child, false];
                }
                continue;
            }
            $parts = [$current->kind, $this->scalar($current->literal), $current->secret ? 'secret' : 'public'];
            foreach ($current->attributes as $key => $value) {
                $parts[] = $key . ':' . $this->scalar($value);
            }
            foreach ($current->operands as $index => $operand) {
                $parts[] = $this->scalar($index) . ':' . $this->keys[$operand];
            }
            $bytes = serialize($parts);
            $this->keys[$current] = $current->isSecret() ? (new SecretFingerprint())->digest($bytes) : hash('sha256', $bytes);
        }
        return $this->keys[$term];
    }

    /**
     * Encodes scalar identity without lossy JSON numeric conversions.
     * @param scalar|null $value Scalar payload
     * @return string Tagged representation
     */
    public function scalar(int|float|string|bool|null $value): string
    {
        if (is_float($value)) {
            return 'f:' . bin2hex(pack('E', $value));
        }
        if (is_string($value)) {
            return 's:' . strlen($value) . ':' . $value;
        }
        if (is_int($value)) {
            return 'i:' . $value;
        }
        return $value === null ? 'null' : ($value ? 'true' : 'false');
    }
}
