<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Name;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Node;

/**
 * The name of a column at a position that defines or alters it, with the table qualifier MySQL 5.x allows there.
 *
 * `CREATE TABLE t (t.a INT)` and `ALTER TABLE t DROP COLUMN shop.t.a` write a
 * qualified column name; the server requires the qualifier to name the table
 * of the statement, so it is kept as written.
 * Source: https://dev.mysql.com/doc/refman/5.7/en/identifier-qualifiers.html.
 *
 * @visibility public
 * @example Holding a qualified column name
 *     $name = new \SqlSemantics\Platform\MySql\Statement\Name\ColumnName(new \SqlSemantics\Statement\Identifier\Name('a'), new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')));
 *     [$name->table?->name->value, $name->column->value] // => ['t', 'a']
 */
final class ColumnName implements Node
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param Name $column The column name
     * @param QualifiedName|null $table The table qualifier, and its database when written
     * @param OptionalWords $dot Whether the unqualified name has the legacy dot prefix
     */
    public function __construct(public readonly Name $column, public readonly ?QualifiedName $table = null, public readonly OptionalWords $dot = OptionalWords::Omitted, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        Check::input($table === null || $table->catalog === null, 'A column name is qualified by a table and at most a database.');
        Check::input($dot === OptionalWords::Omitted || $table === null, 'A definition name with a dot prefix has no table qualifier.');
    }

    /**
     * Resolves the definition qualifier and records its legacy dot-prefix notice.
     */
    public function deriveName(\SqlSemantics\Construction\Derivation $derivation, ?QualifiedName $target = null): void
    {
        if ($this->dot === OptionalWords::Written) {
            \SqlSemantics\Platform\MySql\Statement\Notice\Deprecation::raise(\SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::DotDefinitionName, $derivation, $this);
        }
        $context = $derivation->context;
        $problem = $target === null ? null : (new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Name\ColumnQualifiers())->problem($this, $target, $context->relationNames, $context->declarationSchema);
        if ($problem !== null) {
            $derivation->report($problem);
            $derivation->warn(new \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure($problem, true), $this);
        }
    }

    /**
     * Writes the qualifier parts and the column name.
     */
    public function render(Output $out): void
    {
        if ($this->dot === OptionalWords::Written) {
            (new \SqlSemantics\Platform\MySql\Rendering\LeadingDot())->write($out);
        }
        if ($this->table?->schema !== null) {
            $out->name($this->table->schema, NameUse::Qualifier)->symbol('.');
        }
        if ($this->table !== null) {
            $out->name($this->table->name, NameUse::Qualifier)->symbol('.');
        }
        $out->name($this->column, NameUse::Column);
    }
}
