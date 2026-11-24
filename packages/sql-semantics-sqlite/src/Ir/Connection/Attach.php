<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Connection;

use SqlSemantics\Platform\Sqlite\Ir\Parameter;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\ExpressionScope;
use SqlSemantics\Platform\Sqlite\Ir\Rows\ValueRow;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * An SQLite attachment declaration with typed filename, name and optional key.
 *
 * @visibility public
 * @example Attaching a new temporary in-memory database under a numbered runtime name
 *     $source = new \SqlSemantics\Platform\Sqlite\Ir\Rows\ValueRow([new \SqlSemantics\Platform\Sqlite\Ir\Literal\TextLiteral(':memory:')]);
 *     $name = new \SqlSemantics\Platform\Sqlite\Ir\Parameter('2');
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Connection\Attach($source, $name))->toString() // => "ATTACH (':memory:') AS ?2"
 */
final class Attach implements SemanticsIR
{
    use Snapshot;

    /**
     * Checks the separate scalar roles and the complete statement budget.
     * @throws InvalidConstruction When an operand is misplaced or outside the expanded profile
     */
    public function __construct(
        public readonly ValueRow $file,
        public readonly AttachmentName|Parameter $name,
        public readonly ?ValueRow $key = null,
    ) {
        Check::input(count($file->values) === 1, 'ATTACH needs exactly one filename operand.');
        Check::input($key === null || count($key->values) === 1, 'ATTACH needs exactly one key operand when a key is present.');
        $pending = [[$file->values[0], 0, 0], [$name instanceof AttachmentName ? $name->literal : $name, 0, 0]];
        if ($key !== null) {
            $pending[] = [$key->values[0], 0, 0];
        }
        (new ExpressionScope())->check([], $pending);
    }

    /**
     * Identifies the pinned SQLite language profile.
     */
    public function profileId(): string
    {
        return 'sqlite-3.47.2/utf8/short-column-names/numbered-parameters';
    }

    /**
     * Writes the native filename, name and optional key in evaluation order.
     */
    public function toString(): string
    {
        $name = $this->name instanceof AttachmentName ? $this->name->toString() : '?' . $this->name->label;
        $key = $this->key === null ? '' : ' KEY ' . $this->key->toString();

        return 'ATTACH ' . $this->file->toString() . ' AS ' . $name . $key;
    }
}
