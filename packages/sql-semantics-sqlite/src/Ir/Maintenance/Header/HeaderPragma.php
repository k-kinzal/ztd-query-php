<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance\Header;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * Reads a native database header field or writes a native header cookie.
 *
 * @visibility public
 * @example Reading the main database's user version
 *     $field = \SqlSemantics\Platform\Sqlite\Ir\Maintenance\Header\HeaderCookie::UserVersion;
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Maintenance\Header\HeaderPragma($field))->toString() // => 'PRAGMA "main".user_version'
 */
final class HeaderPragma implements SemanticsIR
{
    use Snapshot;

    /**
     * Fixes the field, resolved database, and optional write operand.
     * @throws InvalidConstruction When a write selects a read-only native field
     */
    public function __construct(
        public readonly HeaderCookie|HeaderReadField $field,
        public readonly Identifier $schema = new Identifier('main'),
        public readonly ?HeaderInteger $value = null,
    ) {
        Check::input($value === null || $field instanceof HeaderCookie, 'A read-only header pragma cannot carry a cookie write.');
    }

    /**
     * Identifies the pinned SQLite language profile.
     */
    public function profileId(): string
    {
        return 'sqlite-3.47.2/utf8/short-column-names/numbered-parameters';
    }

    /**
     * Emits a native header pragma using its resolved database qualifier.
     */
    public function toString(): string
    {
        $argument = $this->value === null ? '' : '(' . $this->value->value . ')';

        return 'PRAGMA ' . $this->schema->quoted() . '.' . $this->field->value . $argument;
    }
}
