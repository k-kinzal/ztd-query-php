<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\MySql\Account;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Account\AccountForms;
use SqlSemantics\Model\Definition\Account\AccountRename;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;

/**
 * RENAME USER: ordered account renames, each keeping username and host separate.
 * @visibility public
 * @example Reading the rename pairs
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("RENAME USER a TO 'b'@'h', c TO d");
 *     [$statement->renames[0]->to->host, $statement->renames[1]->from->username] // => ['h', 'c']
 * @example Rejecting an empty rename list
 *     $origin = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('SELECT 1')->origin;
 *     new \SqlSemantics\Model\Statement\Definition\MySql\Account\RenameUsersStatement($origin, []); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class RenameUsersStatement extends BoundStatement
{
    /**
     * @param non-empty-list<AccountRename> $renames Ordered rename pairs
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(Origin $origin, public readonly array $renames)
    {
        AccountForms::mysql($origin);
        Collections::objects(Collections::nonEmpty($renames), AccountRename::class);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Rename;
    }

    /**
     * Retains the rename pairs while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->renames);
    }

    /**
     * Replaces the rename pairs.
     * @param non-empty-list<AccountRename> $renames Replacement pairs
     */
    public function withRenames(array $renames): self
    {
        return $this->changed(new self($this->origin, $renames));
    }
}
