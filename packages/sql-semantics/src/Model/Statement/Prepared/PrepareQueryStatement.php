<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Prepared;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Statement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Prepares a structured PostgreSQL query with declared parameter types.
 * @example Reading the operation's structure
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build();
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('PREPARE s(int) AS SELECT $1', strict: false);
 *     (new \SqlSemantics\SimpleSerializer())->serialize($statement) // => 'PREPARE "s"(integer) AS SELECT $1'
 *
 * @visibility public
 */
final class PrepareQueryStatement extends BoundStatement
{
    /**
     * @param list<TypeDescriptor> $parameterTypes
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly string $name, public readonly \SqlSemantics\Model\BoundSelect|Statement\ValuesStatement|Statement\TableStatement|Statement\CompoundStatement|Statement\InsertStatement|Statement\UpdateStatement|Statement\DeleteStatement|Statement\MergeStatement $statement, public readonly array $parameterTypes = [])
    {
        if ($origin->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('This prepared-statement form requires PostgreSql.');
        }
        Collections::objects($parameterTypes, TypeDescriptor::class);
        if ($statement->origin->dialect !== $origin->dialect) {
            throw new InvalidStructure('The prepared query must use the enclosing dialect.');
        }
        foreach ($parameterTypes as $type) {
            if ($type->dialect !== $origin->dialect) {
                throw new InvalidStructure('Declared parameter types must use the enclosing dialect.');
            }
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
        return new static($origin, $this->name, $this->statement, $this->parameterTypes);
    }
}
