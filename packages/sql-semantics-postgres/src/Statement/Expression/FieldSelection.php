<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Expression;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * The indirection step `.name`: the selection of a field, or a further part of a dotted name.
 *
 * Source: https://www.postgresql.org/docs/17/sql-expressions.html#FIELD-SELECTION.
 *
 * @visibility public
 * @example Reading the selected field
 *     (new \SqlSemantics\Platform\PostgreSql\Statement\Expression\FieldSelection(new \SqlSemantics\Statement\Identifier\Name('city')))->name->value // => 'city'
 */
final class FieldSelection implements IndirectionStep
{
    use Snapshot;

    /**
     * @param Name $name The field name
     */
    public function __construct(public readonly Name $name)
    {
    }

    /**
     * Answers the field name.
     */
    public function field(): Name
    {
        return $this->name;
    }

    /**
     * Derives nothing: a field name holds no expression.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
    }

    /**
     * Writes the dot and the field name.
     */
    public function render(Output $out): void
    {
        $out->symbol('.')->name($this->name, NameUse::Label);
    }
}
