<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Prepared;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Scalar\Reference;
use SqlSemantics\Model\Scalar\Value;
use SqlSemantics\Model\Statement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Prepares SQL supplied by a text literal or user-variable reference, without evaluating it.
 * @example Reading the operation's structure
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build();
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('PREPARE s FROM @sql', strict: false);
 *     (new \SqlSemantics\SimpleSerializer())->serialize($statement) // => 'PREPARE `s` FROM @`sql`'
 *
 * @visibility public
 */
final class PrepareTextStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly string $name, public readonly Value\Literal|Reference\VariableReference|Reference\UnresolvedVariableReference $sql)
    {
        if ($origin->dialect !== Dialect::MySql) {
            throw new InvalidStructure('This prepared-statement form requires MySql.');
        }
        if ($sql->type->dialect !== $origin->dialect || $sql instanceof Value\Literal && $sql->literalKind !== Value\LiteralKind::Text) {
            throw new InvalidStructure('Dynamic preparation requires a text literal or a MySQL user variable.');
        }
        if ($sql instanceof Reference\VariableReference && $sql->definition->scope !== \SqlSemantics\Schema\VariableScope::User || $sql instanceof Reference\UnresolvedVariableReference && $sql->scope !== \SqlSemantics\Schema\VariableScope::User) {
            throw new InvalidStructure('Dynamic preparation reads only user variables.');
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Prepare;
    }

    /**
     * Retains the prepared-statement operands when replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new static($origin, $this->name, $this->sql);
    }
}
