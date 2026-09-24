<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\MySql\Table;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\MySqlTable\TableRenaming;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Renames one or more tables in request order; a later pair may rename a table produced by an earlier pair.
 * @visibility public
 * @example Swapping two tables through a temporary name
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE a(id INT)', 'CREATE TABLE b(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('RENAME TABLE a TO tmp, b TO a, tmp TO b');
 *     count($statement->renamings) // => 3
 *     $statement->renamings[2]->table->declaration->name // => 'tmp'
 *     $statement->renamings[2]->table->declaration->columns[0]->name // => 'id'
 *     $statement->toString() // => 'RENAME TABLE `a` TO `tmp`, `b` TO `a`, `tmp` TO `b`'
 * @example Rejecting an empty request
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE a(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('RENAME TABLE a TO b');
 *     $statement->withRenamings([]); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class RenameTablesStatement extends BoundStatement
{
    /**
     * @param non-empty-list<TableRenaming> $renamings Renaming pairs applied in order
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly array $renamings)
    {
        TableInvariant::dialect($origin);
        Collections::objects(Collections::nonEmpty($renamings), TableRenaming::class);
        foreach ($renamings as $renaming) {
            TableInvariant::table($origin, $renaming->table);
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Rename;
    }

    /**
     * Retains the renaming pairs while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->renamings);
    }

    /**
     * Replaces the ordered renaming pairs in a separately validated statement.
     * @param non-empty-list<TableRenaming> $renamings Replacement pairs
     */
    public function withRenamings(array $renamings): self
    {
        return $this->changed(new self($this->origin, $renamings));
    }
}
