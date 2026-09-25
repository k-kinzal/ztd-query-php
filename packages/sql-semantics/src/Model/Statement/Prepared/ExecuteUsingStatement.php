<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Prepared;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Scalar\Reference;
use SqlSemantics\Model\Statement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Invokes a MySQL prepared statement using ordered user-variable references.
 * @example Reading the operation's structure
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build();
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('EXECUTE s USING @x, @y', strict: false);
 *     (new \SqlSemantics\SimpleSerializer())->serialize($statement) // => 'EXECUTE `s` USING @`x`, @`y`'
 *
 * @visibility public
 */
final class ExecuteUsingStatement extends BoundStatement
{
    /**
     * @param list<Reference\VariableReference|Reference\UnresolvedVariableReference> $variables
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly string $name, public readonly array $variables = [])
    {
        if ($origin->dialect !== Dialect::MySql) {
            throw new InvalidStructure('This prepared-statement form requires MySql.');
        }
        Collections::alternatives($variables, [Reference\VariableReference::class, Reference\UnresolvedVariableReference::class]);
        foreach ($variables as $variable) {
            $scope = $variable instanceof Reference\VariableReference ? $variable->definition->scope : $variable->scope;
            if ($scope !== \SqlSemantics\Schema\VariableScope::User || $variable->type->dialect !== $origin->dialect) {
                throw new InvalidStructure('EXECUTE USING requires MySQL user-variable references.');
            }
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Execute;
    }

    /**
     * Retains the prepared-statement operands when replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new static($origin, $this->name, $this->variables);
    }
}
