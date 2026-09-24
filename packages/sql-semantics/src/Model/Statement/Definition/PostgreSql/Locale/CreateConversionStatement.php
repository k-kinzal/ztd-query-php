<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\PostgreSql\Locale;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\TypeSystem\Conversion\ServerEncoding;
use SqlSemantics\Model\Definition\TypeSystem\TypeSystemInvariant;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Creates an encoding conversion that a conversion function performs, optionally as the default for its encoding pair.
 * @visibility public
 * @example Creating a default conversion
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("CREATE DEFAULT CONVERSION app.to_latin FOR 'UTF8' TO 'LATIN1' FROM utf8_to_iso8859_1");
 *     $statement->isDefault // => true
 *     $statement->function->parts // => ['utf8_to_iso8859_1']
 *     $statement->toString() // => 'CREATE DEFAULT CONVERSION "app"."to_latin" FOR \'UTF8\' TO \'LATIN1\' FROM "utf8_to_iso8859_1"'
 * @example Rejecting a conversion from SQL_ASCII
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("CREATE CONVERSION c FOR 'UTF8' TO 'LATIN1' FROM f");
 *     $statement->withSourceEncoding(\SqlSemantics\Model\Definition\TypeSystem\Conversion\ServerEncoding::SqlAscii); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class CreateConversionStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly QualifiedName $name, public readonly ServerEncoding $sourceEncoding, public readonly ServerEncoding $targetEncoding, public readonly QualifiedName $function, public readonly bool $isDefault = false)
    {
        TypeSystemInvariant::dialect($origin);
        TypeSystemInvariant::name($name);
        TypeSystemInvariant::name($function);
        if ($sourceEncoding === ServerEncoding::SqlAscii || $targetEncoding === ServerEncoding::SqlAscii) {
            throw new InvalidStructure('An encoding conversion cannot convert to or from SQL_ASCII.');
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Create;
    }

    /**
     * Retains every operand while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->name, $this->sourceEncoding, $this->targetEncoding, $this->function, $this->isDefault);
    }

    /**
     * Replaces the conversion name.
     */
    public function withName(QualifiedName $name): self
    {
        return $this->changed(new self($this->origin, $name, $this->sourceEncoding, $this->targetEncoding, $this->function, $this->isDefault));
    }

    /**
     * Replaces the source encoding.
     */
    public function withSourceEncoding(ServerEncoding $sourceEncoding): self
    {
        return $this->changed(new self($this->origin, $this->name, $sourceEncoding, $this->targetEncoding, $this->function, $this->isDefault));
    }

    /**
     * Replaces the target encoding.
     */
    public function withTargetEncoding(ServerEncoding $targetEncoding): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->sourceEncoding, $targetEncoding, $this->function, $this->isDefault));
    }

    /**
     * Replaces the conversion function.
     */
    public function withFunction(QualifiedName $function): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->sourceEncoding, $this->targetEncoding, $function, $this->isDefault));
    }

    /**
     * Chooses whether the conversion is the default for its encoding pair.
     */
    public function withIsDefault(bool $isDefault): self
    {
        return $this->changed(new self($this->origin, $this->name, $this->sourceEncoding, $this->targetEncoding, $this->function, $isDefault));
    }
}
