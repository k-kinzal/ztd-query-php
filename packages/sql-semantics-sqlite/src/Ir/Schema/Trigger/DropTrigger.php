<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger;

use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Removal\MissingTrigger;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Removal\TriggerReference;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * A resolved SQLite trigger removal or a guarded absence.
 *
 * @visibility public
 * @example Rendering an optional removal in a checked empty catalog
 *     $semantics = new \SqlSemantics\Platform\Sqlite\Semantics();
 *     $semantics->analyze('DROP TRIGGER IF EXISTS audit_orders', $semantics->context([]))->toString() // => 'DROP TRIGGER IF EXISTS "audit_orders"'
 */
final class DropTrigger implements SemanticsIR
{
    use Snapshot;

    /**
     * Requires the existence guard when the external target is absent.
     */
    public function __construct(
        public readonly TriggerReference|MissingTrigger $target,
        public readonly bool $ifExists = false,
    ) {
        Check::input($ifExists || $target instanceof TriggerReference, 'An absent DROP TRIGGER target requires IF EXISTS.');
    }

    /**
     * Identifies the fixed SQLite language profile.
     */
    public function profileId(): string
    {
        return 'sqlite-3.47.2/utf8/short-column-names/numbered-parameters';
    }

    /**
     * Emits the checked selector and native missing-object policy.
     */
    public function toString(): string
    {
        return 'DROP TRIGGER ' . ($this->ifExists ? 'IF EXISTS ' : '') . $this->target->quoted();
    }
}
