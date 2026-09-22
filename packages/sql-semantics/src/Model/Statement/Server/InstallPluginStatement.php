<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Server;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Scalar\Value\LiteralKind;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Loads the named server plugin from a shared library.
 * @example Binding the operation
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build();
 *     $statement = (new \SqlSemantics\Binder($schema))->bind("INSTALL PLUGIN audit SONAME 'audit.so'");
 *     $statement instanceof \SqlSemantics\Model\Statement\Server\InstallPluginStatement // => true
 * @visibility public
 */
final class InstallPluginStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly string $name, public readonly Literal $library)
    {
        if ($origin->dialect !== Dialect::MySql) {
            throw new InvalidStructure('InstallPluginStatement requires MySql.');
        }
        if (($library->type->dialect !== $origin->dialect || $library->literalKind !== LiteralKind::Text)) {
            throw new InvalidStructure('library must retain the statement dialect and be a text literal.');
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Install;
    }

    /**
     * Retains the operation and its operands while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new static($origin, $this->name, $this->library);
    }
}
