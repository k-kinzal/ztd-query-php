<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Type;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharsetForm;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * A character string type with its optional length, national marker and character set attribute.
 *
 * `NCHAR` and `NATIONAL CHAR` are the national CHAR; `NVARCHAR` and its
 * other spellings are the national VARCHAR. `CHAR VARYING` is kept apart
 * from `VARCHAR` because the grammar writes it with two keywords. `LONG` and
 * `LONG VARCHAR` request MEDIUMTEXT.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/string-type-syntax.html.
 *
 * @visibility public
 * @example Reading a character type
 *     $type = new \SqlSemantics\Platform\MySql\Statement\Type\Character(\SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind::VarChar, '255');
 *     [$type->name(), $type->length, $type->national] // => ['VARCHAR', '255', false]
 */
final class Character implements TypeName
{
    use Snapshot;

    /**
     * @param CharacterKind $kind The character type
     * @param string|null $length The length exactly as written
     * @param bool $national Whether the type is written as a national character type; only CHAR and VARCHAR can be
     * @param CharsetAttribute|null $charset The character set attribute; a national type takes BINARY alone
     */
    public function __construct(
        public readonly CharacterKind $kind,
        public readonly ?string $length = null,
        public readonly bool $national = false,
        public readonly ?CharsetAttribute $charset = null,
    ) {
        Check::input($length === null || preg_match('/\A(?:[0-9]+\.?[0-9]*|\.[0-9]+)\z/', $length) === 1, 'A length is an unsigned number.');
        Check::input(!$national || $kind === CharacterKind::Char || $kind === CharacterKind::VarChar, 'Only CHAR and VARCHAR have a national form.');
        Check::input(!$national || $charset === null || $charset->form === CharsetForm::Binary, 'A national character type takes the BINARY attribute alone.');
    }

    /**
     * Names the type by its keywords; a national type by NCHAR or NVARCHAR.
     */
    public function name(): string
    {
        return ($this->national ? 'N' : '') . $this->kind->value;
    }

    /**
     * Writes the keywords, the length and the character set attribute.
     */
    public function render(Output $out): void
    {
        $out->keyword(...explode(' ', $this->name()));
        if ($this->length !== null) {
            $out->glue()->symbol('(')->spelled($this->length)->symbol(')');
        }
        $out->node($this->charset);
    }
}
