<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Type;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Statement;
use SqlSemantics\Platform\PostgreSql\Rules\Catalog\TypeChecks;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;

/**
 * A request to define an enum type: `CREATE TYPE name AS ENUM ( 'label', ... )`.
 *
 * Rule: PG-TYPE-ENUM-001. Mirrors `CreateEnumStmt`: the labels in their
 * sort order. A label longer than 63 bytes is a diagnostic.
 * Source: https://www.postgresql.org/docs/17/sql-createtype.html, https://www.postgresql.org/docs/17/datatype-enum.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class CreateEnum implements Statement
{
    use Snapshot;

    /**
     * @var list<StringConstant> The labels in sort order
     */
    public readonly array $labels;

    /**
     * @param DottedName $name The type name
     * @param list<StringConstant> $labels The labels in sort order
     */
    public function __construct(public readonly DottedName $name, array $labels)
    {
        $this->labels = Check::listOf($labels, StringConstant::class, 'Enum labels are string constants.');
    }

    /**
     * Reports labels longer than the server accepts.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new TypeChecks())->labels($derivation, $this->labels);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('CREATE', 'TYPE')->node($this->name)->keyword('AS', 'ENUM')->symbol('(')->list($this->labels)->symbol(')');
    }
}
