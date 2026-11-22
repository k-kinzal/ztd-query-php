<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Type;

use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\BinaryKind;

/**
 * A binary string type with its optional length.
 *
 * `LONG VARBINARY` requests MEDIUMBLOB. `BLOB(M)` asks for the smallest BLOB
 * type that holds M bytes.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/string-type-syntax.html.
 *
 * @visibility SqlSemantics
 * @example Reading a binary type
 *     $type = new \SqlSemantics\Platform\MySql\Statement\Type\Binary(\SqlSemantics\Platform\MySql\Statement\Type\Kind\BinaryKind::VarBinary, '16');
 *     [$type->name(), $type->length] // => ['VARBINARY', '16']
 */
final class Binary implements TypeName
{
    use Snapshot;

    /**
     * @param BinaryKind $kind The binary type
     * @param string|null $length The length exactly as written; only BINARY, VARBINARY and BLOB take one
     */
    public function __construct(public readonly BinaryKind $kind, public readonly ?string $length = null)
    {
        Check::input($length === null || in_array($kind, [BinaryKind::Binary, BinaryKind::VarBinary, BinaryKind::Blob], true), 'Only BINARY, VARBINARY and BLOB take a length.');
        Check::input($length === null || preg_match('/\A(?:[0-9]+\.?[0-9]*|\.[0-9]+)\z/', $length) === 1, 'A length is an unsigned number.');
    }

    /**
     * Names the type by its keywords.
     */
    public function name(): string
    {
        return $this->kind->value;
    }

    /**
     * Writes the keywords and the length.
     */
    public function render(Output $out): void
    {
        $out->keyword(...explode(' ', $this->kind->value));
        if ($this->length !== null) {
            $out->glue()->symbol('(')->spelled($this->length)->symbol(')');
        }
    }
}
