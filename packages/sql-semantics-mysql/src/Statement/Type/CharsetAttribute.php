<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Type;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\BinaryMark;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * The character set attribute of a character type: ASCII, UNICODE, BYTE, a named character set, BINARY, or a set with BINARY.
 *
 * `ASCII` is CHARACTER SET latin1, `UNICODE` is CHARACTER SET ucs2, `BYTE`
 * makes the type a binary string type, and `BINARY` selects the binary
 * collation of the character set. BINARY may be written before or after the
 * character set; the placement changes nothing and is kept as written.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/string-type-syntax.html,
 * https://dev.mysql.com/doc/refman/8.4/en/charset-binary-collations.html.
 *
 * @visibility public
 * @example Reading a named character set with the binary attribute
 *     $attribute = new \SqlSemantics\Platform\MySql\Statement\Type\CharsetAttribute(\SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm::Named, new \SqlSemantics\Statement\Identifier\Name('utf8mb4'), \SqlSemantics\Platform\MySql\Statement\Type\Kind\BinaryMark::Trailing);
 *     [$attribute->charset?->value, $attribute->binary()] // => ['utf8mb4', true]
 */
final class CharsetAttribute implements Node
{
    use Snapshot;

    /**
     * @param CharsetForm $form How the character set is named
     * @param Name|null $charset The character set name of the named form
     * @param BinaryMark $mark Whether and where BINARY accompanies ASCII, UNICODE or a named character set
     */
    public function __construct(public readonly CharsetForm $form, public readonly ?Name $charset = null, public readonly BinaryMark $mark = BinaryMark::Absent)
    {
        Check::input($form->named() === ($charset !== null), 'Exactly the named forms hold a character set name.');
        Check::input($mark === BinaryMark::Absent || in_array($form, [CharsetForm::Ascii, CharsetForm::Unicode, CharsetForm::Named, CharsetForm::CharacterSet], true), 'BINARY accompanies ASCII, UNICODE or a named character set only.');
    }

    /**
     * Tells whether the attribute selects a binary collation.
     */
    public function binary(): bool
    {
        return $this->form === CharsetForm::Binary || $this->mark !== BinaryMark::Absent;
    }

    /**
     * Writes the attribute with BINARY at its written place.
     */
    public function render(Output $out): void
    {
        if ($this->mark === BinaryMark::Leading) {
            $out->keyword('BINARY');
        }
        $out->keyword(...explode(' ', $this->form->value));
        if ($this->charset !== null) {
            $out->name($this->charset, NameUse::Label);
        }
        if ($this->mark === BinaryMark::Trailing) {
            $out->keyword('BINARY');
        }
    }
}
