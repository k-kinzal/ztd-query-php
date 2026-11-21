<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir;

use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A decoded identifier or output label.
 *
 * @visibility public
 * @example Quoting a reserved word
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Identifier('select'))->quoted() // => '"select"'
 */
final class Identifier
{
    use Snapshot;

    /**
     * Checks the name against the language profile.
     */
    public function __construct(public readonly string $value)
    {
        Check::input(
            $value !== '' && strlen($value) <= 63 && !str_contains($value, "\0") && preg_match('//u', $value) === 1,
            'A PostgreSQL IR name must be nonempty UTF-8, without NUL, and at most 63 bytes.',
        );
    }

    /**
     * Quotes this name in an identifier position.
     */
    public function quoted(): string
    {
        return '"' . str_replace('"', '""', $this->value) . '"';
    }
}
