<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * The removal of an SQLite view at an absolute schema location.
 *
 * @visibility public
 * @example Addressing an optional drop to the temp schema
 *     $schema = new \SqlSemantics\Platform\Sqlite\Ir\Identifier('temp');
 *     $name = new \SqlSemantics\Platform\Sqlite\Ir\Identifier('scratch');
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Schema\DropView($schema, $name, true))->toString() // => 'DROP VIEW IF EXISTS "temp"."scratch"'
 */
final class DropView implements SemanticsIR
{
    use Snapshot;

    /**
     * Fixes the resolved view identity and the missing-object policy.
     */
    public function __construct(
        public readonly Identifier $schema,
        public readonly Identifier $view,
        public readonly bool $ifExists = false,
    ) {
        Check::input($schema->value !== '', 'A DROP VIEW needs a nonempty schema identifier.');
        Check::input(!str_starts_with($view->key(), 'sqlite_'), 'A DROP VIEW cannot target the reserved SQLite namespace.');
    }

    /**
     * Identifies the fixed SQLite language profile.
     */
    public function profileId(): string
    {
        return 'sqlite-3.47.2/utf8/short-column-names/numbered-parameters';
    }

    /**
     * Emits the resolved target and the policy for an absent object.
     */
    public function toString(): string
    {
        return 'DROP VIEW ' . ($this->ifExists ? 'IF EXISTS ' : '') . $this->schema->quoted() . '.' . $this->view->quoted();
    }
}
