<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Inspection\Server;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\Query\Inspection\Field\Session\ParseTreeField;
use SqlSemantics\Model\Statement\Inspection\InspectionStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Returns the server's parse tree of a statement as JSON without executing it; available from MySQL 8.1.
 * @visibility public
 * @example Inspecting the parsed statement
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('SHOW PARSE_TREE SELECT 1');
 *     [$statement->statement->kind->value, $statement->resultColumns()[0]->name] // => ['SELECT', 'Parse_tree']
 */
final class ShowParseTreeStatement extends InspectionStatement
{
    /**
     * @param BoundStatement $statement Parsed statement, bound but never executed
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly BoundStatement $statement)
    {
        if ($origin->dialect !== $statement->origin->dialect) {
            throw new InvalidStructure('A parse tree request and its statement must use one dialect.');
        }
        if (in_array($origin->context?->schema()->grammarVersion, ['mysql-5.6.51', 'mysql-5.7.44', 'mysql-8.0.44'], true)) {
            throw new InvalidStructure('SHOW PARSE_TREE requires MySQL 8.1 or later.');
        }
        parent::__construct($origin);
    }

    /**
     * Retains the request while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->statement);
    }

    /**
     * Parses another statement.
     */
    public function withStatement(BoundStatement $statement): self
    {
        return $this->changed(new self($this->origin, $statement));
    }

    /**
     * @return list<OutputColumn>
     */
    #[Override]
    public function resultColumns(): array
    {
        return $this->columns(ParseTreeField::cases());
    }
}
