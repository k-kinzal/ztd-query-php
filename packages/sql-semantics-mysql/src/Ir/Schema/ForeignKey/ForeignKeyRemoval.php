<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey;

use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * The native constraint operand relative to the checked ALTER TABLE owner.
 * @visibility public
 * @example Emitting one resolved native foreign-key name
 *     $name = new \SqlSemantics\Platform\MySql\Ir\Identifier('owner_ref');
 *     $kind = \SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\ForeignKeyRemovalKind::Constraint;
 *     (new \SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\ForeignKeyRemoval($name, $kind))->toString() // => 'DROP FOREIGN KEY `owner_ref`'
 */
final class ForeignKeyRemoval
{
    use Snapshot;

    /**
     * Checks the resolved native name for a persistent action.
     */
    public function __construct(public readonly ?Identifier $name, public readonly ForeignKeyRemovalKind $kind)
    {
        Check::input(($name === null) === ($kind === ForeignKeyRemovalKind::IgnoredOnTemporary), 'Only a resolved persistent foreign-key action has a physical name.');
        Check::input($name === null || preg_match('/\A.{0,64}\z/us', $name->value) === 1, 'A native foreign-key name has at most 64 characters.');
    }

    /**
     * Emits a relative physical name or the canonical ignored label.
     */
    public function toString(): string
    {
        return 'DROP FOREIGN KEY ' . ($this->name?->quoted() ?? '``');
    }
}
