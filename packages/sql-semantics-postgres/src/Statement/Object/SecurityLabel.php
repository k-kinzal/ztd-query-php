<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Object;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Routine\ObjectFacts;
use SqlSemantics\Platform\PostgreSql\Rules\Routine\ObjectForms;
use SqlSemantics\Platform\PostgreSql\Rules\Routine\ObjectSpelling;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectReference;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Word;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `SECURITY LABEL [FOR provider] ON kind object IS 'label' | NULL`: sets or removes a security label.
 *
 * Mirrors PostgreSQL's `SecLabelStmt`. Without a provider the only loaded
 * label provider is used. A labelled relation or column is resolved.
 * Source: https://www.postgresql.org/docs/17/sql-security-label.html.
 *
 * @visibility public
 * @example Reading the provider
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze("SECURITY LABEL FOR selinux ON SCHEMA app IS 'x'");
 *     $operation->statement->provider?->word->value // => 'selinux'
 */
final class SecurityLabel implements Statement
{
    use Snapshot;

    /**
     * @param ObjectKind $kind The kind of the object
     * @param ObjectReference $object The object, named as SECURITY LABEL names objects of the kind
     * @param StringConstant|null $label The label; null for NULL
     * @param Word|StringConstant|null $provider The label provider, if written
     */
    public function __construct(
        public readonly ObjectKind $kind,
        public readonly ObjectReference $object,
        public readonly ?StringConstant $label,
        public readonly Word|StringConstant|null $provider = null,
    ) {
        $forms = new ObjectForms();
        Check::input(in_array($forms->form($object), $forms->label($kind), true), 'SECURITY LABEL names the object as the grammar names objects of its kind.');
    }

    /**
     * Derives the object.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new ObjectFacts())->derive($this->kind, [$this->object], false, $derivation);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('SECURITY', 'LABEL');
        if ($this->provider !== null) {
            $out->keyword('FOR')->node($this->provider);
        }
        $out->keyword('ON');
        (new ObjectSpelling())->kind($out, $this->kind);
        $out->node($this->object)->keyword('IS');
        if ($this->label === null) {
            $out->keyword('NULL');

            return;
        }
        $out->node($this->label);
    }
}
