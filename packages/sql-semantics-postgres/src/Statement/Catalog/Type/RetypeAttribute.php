<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Type;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\Option\DropBehavior;
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * `ALTER ATTRIBUTE name [ SET DATA ] TYPE type [ COLLATE collation ] [ CASCADE | RESTRICT ]`.
 *
 * Mirrors the `AlterTableCmd` AT_AlterColumnType of a composite type; the
 * optional SET DATA is not kept.
 * Source: https://www.postgresql.org/docs/17/sql-altertype.html.
 *
 * @visibility public
 * @example Changing the type of an attribute
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER TYPE pair ALTER ATTRIBUTE a SET DATA TYPE int8');
 *     $operation->toString() // => 'ALTER TYPE pair ALTER ATTRIBUTE a TYPE int8'
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
