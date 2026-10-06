<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Name;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rendering\LeadingDot;
use SqlSemantics\Platform\MySql\Rules\ColumnResolver;
use SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * A name used as a value: a request to resolve it as a column at its position.
 *
 * The node holds the decoded column name and its optional table and database
 * qualifiers. Which occurrence and declaration it denotes is a fact of the
 * operation that contains it.
 *
 * Rule: MYSQL-COLUMN-USE-001. Facts: the resolution of MYSQL-COLUMN-LOOKUP-001
 * over the relation occurrences of the position and, after them, the select
 * list aliases the position may use; a use resolved to a slot or to an
 * aliased item has the type and NULL fact of that slot or item; a
 * conditional use depends on its missing inputs; a missing or ambiguous use
 * is invalid and is a diagnostic. Terminates: the lookup is finite.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/identifier-qualifiers.html,
 * https://dev.mysql.com/doc/refman/8.4/en/problems-with-alias.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading a qualified column use
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT shop.t.a FROM shop.t');
 *     $use = $query->statement->items[0]->expression;
 *     [$use->qualifier?->schema?->value, $use->qualifier?->name->value, $use->name->value] // => ['shop', 't', 'a']
 */
final class ColumnUse implements Scalar
{
    use Snapshot;

    /**
     * @param Name $name The column name
     * @param QualifiedName|null $qualifier The table the use is qualified with, and the database of that table when written
     * @param OptionalWords $dot Whether the table qualifier is written after a leading dot, `.t.a` (MySQL 5.6 and 5.7)
     */
    public function __construct(public readonly Name $name, public readonly ?QualifiedName $qualifier = null, public readonly OptionalWords $dot = OptionalWords::Omitted)
    {
        Check::input($qualifier === null || $qualifier->catalog === null, 'A column is qualified by a table and at most a database.');
        Check::input($dot === OptionalWords::Omitted || ($qualifier !== null && $qualifier->schema === null), 'Only a table qualifier without database is written after a leading dot.');
    }

    /**
     * Resolves the name in the environment and derives its facts from the slot found.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        return (new ColumnResolver())->fact($environment, $this->name, $this->qualifier);
    }

    /**
     * Writes the qualifier parts and the name.
     */
    public function render(Output $out): void
    {
        if ($this->dot === OptionalWords::Written) {
            (new LeadingDot())->write($out);
        }
        if ($this->qualifier?->schema !== null) {
            $out->name($this->qualifier->schema, NameUse::Qualifier)->symbol('.');
        }
        if ($this->qualifier !== null) {
            $out->name($this->qualifier->name, NameUse::Qualifier)->symbol('.');
        }
        $out->name($this->name, NameUse::Column);
    }
}
