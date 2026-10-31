<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Notice\NameConversion;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;

/**
 * A resource-group identifier of at most 64 bytes after conversion to utf8mb3.
 *
 * The decoded spelling remains an operand leaf. Supplementary Unicode code points
 * become question marks in the group identity; their conversion raises a warning.
 * Empty names and surrounding whitespace are retained. Observed on MySQL 8.0.44,
 * 8.4.7 and 9.1.0. Source: https://dev.mysql.com/doc/refman/8.4/en/resource-groups.html.
 *
 * @visibility public
 * @example Reading a converted group identity
 *     (new \SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\ResourceGroupName(new \SqlSemantics\Statement\Identifier\Name('batch😀')))->value // => 'batch?'
 */
final class ResourceGroupName implements Node
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * The group identity after character conversion, with case preserved.
     */
    public readonly string $value;

    /**
     * @param Name $spelling The decoded identifier before character conversion
     */
    public function __construct(public readonly Name $spelling, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        Check::input(!str_contains($spelling->value, "\0"), 'A resource-group identifier cannot contain NUL.');
        Check::input(self::accepts($spelling), 'A resource-group name occupies at most 64 utf8mb3 bytes.');
        $this->value = self::decoded($spelling);
    }

    /**
     * Tests the byte limit after supplementary Unicode characters are replaced.
     */
    public static function accepts(Name $spelling): bool
    {
        return strlen(self::decoded($spelling)) <= 64;
    }

    /**
     * Returns the identifier with each four-byte UTF-8 code point replaced.
     */
    public static function decoded(Name $spelling): string
    {
        return preg_replace('/[\xF0-\xF4][\x80-\xBF]{3}/', '?', $spelling->value) ?? $spelling->value;
    }

    /**
     * Reports the character conversion of the operand, when necessary.
     */
    public function derive(Derivation $derivation): void
    {
        if ($this->value !== $this->spelling->value) {
            $derivation->warn(new NameConversion($this->spelling), $this);
        }
    }

    /**
     * Writes the retained decoded spelling as an identifier.
     */
    public function render(Output $out): void
    {
        $out->name($this->spelling, NameUse::Label);
    }
}
