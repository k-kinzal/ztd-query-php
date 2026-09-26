<?php

declare(strict_types=1);

namespace Requirements\Model;

use InvalidArgumentException;
use Requirements\Config\Fields;

final class Source
{
    /** @param array<string, mixed> $options */
    public function __construct(
        public readonly string $id,
        public readonly string $uri,
        public readonly string $format,
        public readonly string $selector,
        public readonly ?string $snapshot = null,
        public readonly ?string $sha256 = null,
        public readonly array $options = [],
    ) {
    }

    public static function from(mixed $value): self
    {
        $data = Fields::mapping($value, 'source');
        Fields::keys($data, ['id', 'uri', 'format', 'selector', 'snapshot', 'sha256', 'options'], 'source');
        $snapshot = isset($data['snapshot']) ? Fields::text($data, 'snapshot') : null;
        $hash = isset($data['sha256']) ? Fields::text($data, 'sha256') : null;
        if (($snapshot !== null && $hash === null) || ($hash !== null && preg_match('/^[a-f0-9]{64}$/D', $hash) !== 1)) {
            throw new InvalidArgumentException('A snapshot requires a lowercase SHA-256 digest.');
        }
        return new self(Fields::text($data, 'id'), Fields::text($data, 'uri'), Fields::text($data, 'format'), Fields::text($data, 'selector'), $snapshot, $hash, Fields::mapping($data['options'] ?? [], 'source.options'));
    }
}
