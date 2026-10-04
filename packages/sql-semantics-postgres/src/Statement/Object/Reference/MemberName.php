<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Object\Reference;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Rendering\Spelling;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectReference;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Snapshot;

/**
 * An object named within the table or domain it belongs to: `name ON table` or `name ON DOMAIN domain`.
 *
 * Policies, rules, triggers and table constraints are named per table, and
 * domain constraints per domain. DROP and COMMENT write the owner as a dotted
 * name of any length, ALTER ... RENAME and DEPENDS ON EXTENSION as a relation
 * name of at most three parts; the form is kept.
 * Source: https://www.postgresql.org/docs/17/sql-droptrigger.html, https://www.postgresql.org/docs/17/sql-comment.html.
 *
 * @visibility public
 * @example Reading a trigger of a table
 *     $member = new \SqlSemantics\Platform\PostgreSql\Statement\Object\Reference\MemberName(new \SqlSemantics\Statement\Identifier\Name('audit'), new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('orders')));
 *     [$member->name->value, $member->table()?->name->value] // => ['audit', 'orders']
 */
final class MemberName implements ObjectReference
{
    use Snapshot;

    /**
     * @param Name $name The object name
     * @param DottedName|QualifiedName $owner The table or domain the object belongs to
     * @param bool $domain Whether the owner is a domain, written ON DOMAIN
     */
    public function __construct(public readonly Name $name, public readonly DottedName|QualifiedName $owner, public readonly bool $domain = false)
    {
    }

    /**
     * Answers the owning table as a relation name, or null when the owner is a domain or has more than three parts.
     */
    public function table(): ?QualifiedName
    {
        if ($this->domain) {
            return null;
        }

        return $this->owner instanceof QualifiedName ? $this->owner : $this->owner->qualified();
    }

    /**
     * Derives nothing: names hold no expression.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
    }

    /**
     * Writes the name, ON, DOMAIN when the owner is a domain, and the owner.
     */
    public function render(Output $out): void
    {
        $out->name($this->name, NameUse::Column)->keyword('ON');
        if ($this->domain) {
            $out->keyword('DOMAIN');
        }
        if ($this->owner instanceof QualifiedName) {
            (new Spelling())->qualified($out, $this->owner);

            return;
        }
        $out->node($this->owner);
    }
}
