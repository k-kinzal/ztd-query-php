<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Type;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Option\DropBehavior;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;

/**
 * `ALTER ATTRIBUTE name [ SET DATA ] TYPE type [ COLLATE collation ] [ CASCADE | RESTRICT ]`.
 *
 * Mirrors the `AlterTableCmd` AT_AlterColumnType of a composite type; the
 * optional SET DATA is not kept.
 * Source: https://www.postgresql.org/docs/17/sql-altertype.html.
 *
 * @visibility SqlSemantics
 */
final class RetypeAttribute implements AttributeChange
{
    use Snapshot;

    /**
     * @param Name $name The attribute name
     * @param TypeName $type The new type
     * @param DottedName|null $collation The collation, when COLLATE is written
     * @param DropBehavior|null $behavior Whether typed tables are changed too, when written
     */
    public function __construct(public readonly Name $name, public readonly TypeName $type, public readonly ?DottedName $collation = null, public readonly ?DropBehavior $behavior = null)
    {
    }

    /**
     * Derives the type.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        $this->type->deriveClause($derivation, $environment);
    }

    /**
     * Writes the change.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER', 'ATTRIBUTE')->name($this->name, NameUse::Column)->keyword('TYPE')->node($this->type);
        if ($this->collation !== null) {
            $out->keyword('COLLATE')->node($this->collation);
        }
        if ($this->behavior !== null) {
            $out->keyword($this->behavior->value);
        }
    }
}
