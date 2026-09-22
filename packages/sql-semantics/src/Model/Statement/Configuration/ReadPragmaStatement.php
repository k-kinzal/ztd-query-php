<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Configuration;

use Override;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;

/**
 * ReadPragmaStatement requires the operands of this SQL operation.
 *
 * @visibility public
  * @example Inspecting ReadPragmaStatement
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::Sqlite))->build());
 *     $statement = $binder->bind('PRAGMA main.cache_size=-2000');
 *     $read = $binder->bind('PRAGMA cache_size');
 *     $read instanceof \SqlSemantics\Model\Statement\Configuration\ReadPragmaStatement // => true
 */
final class ReadPragmaStatement extends \SqlSemantics\Model\Statement\ConfigurationStatement
{
    /**

     */
    public function __construct(
        Origin $origin,
        public readonly \SqlSemantics\Model\Relation\QualifiedName $name,
    ) {
        parent::__construct($origin);
    }

    /**
     * Returns the operation selected by this concrete type.
     */
    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Pragma;
    }

    /**
     * Retains operands while replacing diagnostic provenance.
     * @visibility SqlSemantics
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new static($origin, $this->name);
    }
}
