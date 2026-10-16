<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Notice;

use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * A decoded identifier contains Unicode characters outside the system character set.
 *
 * The diagnostic retains the full operand; its message displays the first six bytes.
 * Observed on MySQL 8.0.44, 8.4.7 and 9.1.0 with utf8mb4 client input.
 *
 * @visibility public
 * @example Describing character conversion
 *     (new \SqlSemantics\Platform\MySql\Statement\Notice\NameConversion(new \SqlSemantics\Statement\Identifier\Name('😀')))->code() // => 1300
 */
final class NameConversion implements CodedWarning
{
    use Snapshot;

    /**
     * @param Name $name The decoded identifier before character conversion
     * @param bool $legacy Whether the release reports an invalid character string instead of naming the conversion
     */
    public function __construct(public readonly Name $name, public readonly bool $legacy = false)
    {
    }

    /**
     * Returns the server warning number.
     */
    public function code(): int
    {
        return 1300;
    }

    /**
     * Describes the lossy conversion using the server's byte excerpt.
     */
    public function message(): string
    {
        $excerpt = '';
        foreach (str_split(substr($this->name->value, 0, 6)) as $byte) {
            $excerpt .= ord($byte) >= 32 && ord($byte) <= 126 ? $byte : '\\x' . strtoupper(bin2hex($byte));
        }

        $excerpt .= strlen($this->name->value) > 6 ? '...' : '';

        return $this->legacy ? "Invalid utf8mb4 character string: '" . $excerpt . "'" : "Cannot convert string '" . $excerpt . "' from utf8mb4 to utf8mb3";
    }
}
