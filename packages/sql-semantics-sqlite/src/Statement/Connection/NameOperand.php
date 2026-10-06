<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Connection;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

/**
 * An identifier written as an operand of ATTACH or DETACH, which SQLite reads as a text value.
 *
 * Rule: SQLITE-ATTACH-NAME-001. The operands of ATTACH and DETACH are
 * expressions, but an operand that is a single identifier, with or without
 * parentheses around it, is not a column reference: SQLite turns it into the
 * string of its name before resolving names, which is what makes
 * `ATTACH 'file.db' AS aux` work. Facts: TEXT, never NULL, no resolution.
 * Source: https://sqlite.org/lang_attach.html (and `resolveAttachExpr()` in
 * attach.c of the release). Status: Implemented.
 *
 * @visibility public
 * @example Reading a schema name written as an identifier
 *     $attach = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze("ATTACH 'file.db' AS aux");
 *     [$attach->statement->schema->name->value, $attach->facts->scalar($attach->statement->schema)->type->descriptor] // => ['aux', \SqlSemantics\Platform\Sqlite\Statement\Type\Storage::Text]
 */
final class NameOperand implements Scalar
{
    use Snapshot;

    /**
     * @param Name $name The identifier whose name is the text value
     */
    public function __construct(public readonly Name $name)
    {
    }

    /**
     * Derives the facts of a text constant.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        return new ScalarFact(new Known(Storage::Text), Nullability::NotNull);
    }

    /**
     * Writes the identifier.
     */
    public function render(Output $out): void
    {
        $out->name($this->name, NameUse::Label);
    }
}
