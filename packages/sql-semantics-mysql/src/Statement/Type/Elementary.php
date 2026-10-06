<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Type;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * A type written as one keyword: BOOL, SERIAL, JSON, and BIT or VECTOR with an optional length.
 *
 * `BOOLEAN` is `BOOL`; both request TINYINT(1). `SERIAL` requests
 * BIGINT UNSIGNED NOT NULL AUTO_INCREMENT UNIQUE. The request is kept as
 * written; the expansion is a rule of the statement that declares a column.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/numeric-type-syntax.html,
 * https://dev.mysql.com/doc/refman/8.4/en/json.html,
 * https://dev.mysql.com/doc/refman/9.1/en/vector.html.
 *
 * @visibility public
 * @example Reading a bit type
 *     $type = new \SqlSemantics\Platform\MySql\Statement\Type\Elementary(\SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind::Bit, '8');
 *     [$type->name(), $type->length] // => ['BIT', '8']
 */
final class Elementary implements TypeName
{
    use Snapshot;

    /**
     * @param ElementaryKind $kind The type
     * @param string|null $length The length exactly as written; only BIT and VECTOR take one
     */
    public function __construct(public readonly ElementaryKind $kind, public readonly ?string $length = null)
    {
        Check::input($length === null || $kind === ElementaryKind::Bit || $kind === ElementaryKind::Vector, 'Only BIT and VECTOR take a length.');
        Check::input($length === null || preg_match('/\A(?:[0-9]+\.?[0-9]*|\.[0-9]+)\z/', $length) === 1, 'A length is an unsigned number.');
    }

    /**
     * Names the type by its keyword.
     */
    public function name(): string
    {
        return $this->kind->value;
    }

    /**
     * Writes the keyword and the length.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->kind->value);
        if ($this->length !== null) {
            $out->glue()->symbol('(')->spelled($this->length)->symbol(')');
        }
    }
}
