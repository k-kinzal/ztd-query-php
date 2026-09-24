<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation\Identity;

use SqlSemantics\Model\Definition\Catalog\CatalogInvariant;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Names the sequence that backs an identity column instead of letting the server choose.
 * @visibility public
 * @example Reading the sequence name
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t ALTER COLUMN id ADD GENERATED ALWAYS AS IDENTITY (SEQUENCE NAME app.t_id_seq)');
 *     $statement->actions[0]->options[0]->name->parts // => ['app', 't_id_seq']
 */
final class SetSequenceName
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly QualifiedName $name)
    {
        CatalogInvariant::name($name, 3);
    }
}
