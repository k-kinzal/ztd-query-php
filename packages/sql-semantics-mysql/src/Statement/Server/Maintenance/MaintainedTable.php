<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Maintenance;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Node;

/**
 * One table a server administration statement names in its table list: the position its resolution is recorded at.
 *
 * The statement that holds it records the resolution of the name as the
 * relation fact of this node (MYSQL-SERVER-TABLES-001).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/table-maintenance-statements.html.
 *
 * @visibility public
 * @example Holding a table name with its database
 *     $table = new \SqlSemantics\Platform\MySql\Statement\Server\Maintenance\MaintainedTable(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t'), new \SqlSemantics\Statement\Identifier\Name('shop')));
 *     $table->name->schema?->value // => 'shop'
 */
final class MaintainedTable implements Node, \SqlSemantics\Statement\TableUse
{
    use \SqlSemantics\Statement\Semantics\RelationSemantics;
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param QualifiedName $name The table name with its optional database
     * @param \SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords $dot Whether the legacy unqualified name has a leading dot
     */
    public function __construct(public readonly QualifiedName $name, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null, ?\SqlSemantics\Statement\Fact\RelationFact $relationInformation = null, public readonly \SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords $dot = \SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted)
    {
        if ($relationInformation !== null) {
            $this->initializeRelationInformation($relationInformation);
        }
        (new \SqlSemantics\Platform\MySql\Rules\TableChange\TableIdentity())->check($name);
        Check::input($name->catalog === null, 'A table is qualified by at most a database.');
        Check::input($dot === \SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted || $name->schema === null, 'Only a table name without database is written after a leading dot.');
    }

    /**
     * Writes the name.
     */
    public function render(Output $out): void
    {
        if ($this->dot === \SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Written) {
            (new \SqlSemantics\Platform\MySql\Rendering\LeadingDot())->write($out);
        }
        if ($this->name->schema !== null) {
            $out->name($this->name->schema, NameUse::Qualifier)->symbol('.');
        }
        $out->name($this->name->name, NameUse::Relation);
    }
}
