<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir;

use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A decoded identifier or output label.
 *
 * @visibility public
 * @example Quoting a reserved word
 *     (new \SqlSemantics\Platform\MySql\Ir\Identifier('select'))->quoted() // => '`select`'
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
            preg_match('/\A[^\x00\x{10000}-\x{10FFFF}]{0,256}\z/u', $value) === 1,
            'A MySQL IR label must have up to 256 BMP characters and no NUL.',
        );
    }

    /**
     * Quotes this name in an identifier position.
     */
    public function quoted(): string
    {
        return '`' . str_replace('`', '``', $this->value) . '`';
    }

    /**
     * Folds the ASCII case-insensitive part of the column identifier comparison.
     */
    public function key(): string
    {
        return Comparison::AsciiInsensitive->fold($this->value);
    }
}
