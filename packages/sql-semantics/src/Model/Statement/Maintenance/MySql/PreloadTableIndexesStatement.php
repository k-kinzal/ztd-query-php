<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Maintenance\MySql;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Maintenance\IndexCache\PreloadTarget;
use SqlSemantics\Model\Maintenance\MySql\ResultColumns;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\ResultStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Preloads index pages using their current cache assignment for one or more whole tables.
 * @visibility public
 * @example Inspecting the operation without changing server memory
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $request = (new \SqlSemantics\Binder($schema))->bind('LOAD INDEX INTO CACHE t');
 *     $request instanceof \SqlSemantics\Model\Statement\Maintenance\MySql\PreloadTableIndexesStatement // => true
 * @example Rejecting a missing table request
 *     $origin = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('SELECT 1')->origin;
 *     new \SqlSemantics\Model\Statement\Maintenance\MySql\PreloadTableIndexesStatement($origin, []); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class PreloadTableIndexesStatement extends BoundStatement implements ResultStatement
{
    /**
     * @param non-empty-list<PreloadTarget> $targets Ordered table requests
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly array $targets,
    ) {
        if ($origin->dialect !== Dialect::MySql) {
            throw new InvalidStructure('Index-cache operations require MySQL.');
        }
        Collections::objects(Collections::nonEmpty($targets), PreloadTarget::class);
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Load;
    }

    /**
     * Retains the request when changing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->targets);
    }

    /**
     * Replaces the targets in an independently validated request.
     * @param non-empty-list<PreloadTarget> $targets Ordered replacements
     */
    public function withTargets(array $targets): self
    {
        return $this->changed(new self($this->origin, $targets));
    }

    /**
     * @return list<OutputColumn> Table, operation, message category, and message text
     */
    #[Override]
    public function resultColumns(): array
    {
        return ResultColumns::status($this->origin);
    }
}
