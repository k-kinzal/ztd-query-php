<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Connection;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Literal\TextLiteral;
use SqlSemantics\Platform\Sqlite\Ir\NullLiteral;
use SqlSemantics\Platform\Sqlite\Ir\SelectSql;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * The typed constant and native name of a non-builtin SQLite attachment.
 *
 * @visibility public
 * @example Choosing a database attachment name
 *     $word = new \SqlSemantics\Platform\Sqlite\Ir\Literal\TextLiteral('archive');
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Connection\AttachmentName($word))->name->value // => 'archive'
 */
final class AttachmentName
{
    use Snapshot;

    /**
     * The name reached by SQLite's native text conversion.
     */
    public readonly Identifier $name;

    /**
     * Fixes the literal type and derives its SQLite attachment name.
     * @throws InvalidConstruction When the selected name is builtin or outside the identifier profile
     */
    public function __construct(public readonly TextLiteral|IntegerLiteral|NullLiteral $literal)
    {
        $value = match (true) {
            $literal instanceof TextLiteral => explode("\0", $literal->value, 2)[0],
            $literal instanceof IntegerLiteral => $literal->decimal,
            $literal instanceof NullLiteral => '',
        };
        $name = new Identifier($value);
        Check::input($name->key() !== 'main' && $name->key() !== 'temp', 'A constant attachment name cannot select a builtin database.');
        $this->name = $name;
    }

    /**
     * Emits the checked constant in the native name position.
     */
    public function toString(): string
    {
        return (new SelectSql())->expression($this->literal, null);
    }
}
