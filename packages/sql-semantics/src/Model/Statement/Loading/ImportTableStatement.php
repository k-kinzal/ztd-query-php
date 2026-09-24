<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Loading;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Configuration\Replication\ReplicationRelease;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Scalar\Value\LiteralKind;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Imports MyISAM tables from serialized dictionary information files; each pattern names .sdi files on the server.
 * @visibility public
 * @example Reading the imported file patterns
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("IMPORT TABLE FROM '/tmp/t1.sdi', '/tmp/t2*.sdi'");
 *     array_map(static fn ($file) => $file->text, $statement->files) // => ["'/tmp/t1.sdi'", "'/tmp/t2*.sdi'"]
 */
final class ImportTableStatement extends BoundStatement
{
    /**
     * @var non-empty-list<Literal>
     */
    public readonly array $files;

    /**
     * @param list<Literal> $files File name patterns in request order, each a text literal
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, array $files)
    {
        ReplicationRelease::require($origin, 'IMPORT TABLE', 80000);
        $this->files = Collections::nonEmpty($files);
        foreach ($this->files as $file) {
            if ($file->type->dialect !== $origin->dialect || $file->literalKind !== LiteralKind::Text) {
                throw new InvalidStructure('IMPORT TABLE requires MySQL text literals naming its files.');
            }
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Import;
    }

    /**
     * Retains the file patterns while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->files);
    }

    /**
     * Imports another nonempty list of file patterns.
     * @param list<Literal> $files
     */
    public function withFiles(array $files): self
    {
        return $this->changed(new self($this->origin, $files));
    }
}
