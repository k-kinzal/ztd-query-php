<?php

declare(strict_types=1);

namespace Fuzz\Shared\Input;

/**
 * Expected rejection is declared by input construction, never inferred from ZTD's answer.
 */
final class Command
{
    /**
     * Describe one SQL operation and its independently declared expectation.
     */
    public function __construct(
        public readonly string $sql,
        public readonly string $kind = 'read',
        public readonly ?string $rejection = null,
        public readonly ?string $feature = null,
    ) {
    }
}
