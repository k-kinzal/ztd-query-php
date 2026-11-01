<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Tablespace;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Catalog\ClauseFacts;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpec;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Definition;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Statement;

/**
 * A request to create a tablespace.
 *
 * Rule: PG-TABLESPACE-001. Mirrors `CreateTableSpaceStmt`: name, owner,
 * location and the tablespace parameters of WITH ( ... ).
 * Source: https://www.postgresql.org/docs/17/sql-createtablespace.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the location of a new tablespace
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze("CREATE TABLESPACE fast OWNER app LOCATION '/ssd' WITH (random_page_cost = 1)");
 *     [$operation->location->value, $operation->options[0]->name->value] // => ['/ssd', 'random_page_cost']
 */
final class CreateTablespace extends \SqlSemantics\Statement\SqlStatement implements Statement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @var list<Definition> The tablespace parameters
     */
    public readonly array $options;

    /**
     * @param Name $name The tablespace name
     * @param RoleSpec|null $owner The owner, when OWNER is written
     * @param StringConstant $location The directory
     * @param list<Definition> $options The tablespace parameters
     */
    public function __construct(public readonly Name $name, public readonly ?RoleSpec $owner, public readonly StringConstant $location, array $options = [], public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null, ?\SqlSemantics\Statement\StatementSemantics $statementInformation = null)
    {
        if ($statementInformation !== null) {
            $this->initializeStatementInformation($statementInformation);
        }
        $this->options = Check::listOf($options, Definition::class, 'Tablespace parameters are a list of definitions.');
    }

    /**
     * Derives the parameter values.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new ClauseFacts())->derive($derivation, $this->options);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('CREATE', 'TABLESPACE')->name($this->name, NameUse::Column);
        if ($this->owner !== null) {
            $out->keyword('OWNER')->node($this->owner);
        }
        $out->keyword('LOCATION')->node($this->location);
        if ($this->options !== []) {
            $out->keyword('WITH')->symbol('(')->list($this->options)->symbol(')');
        }
    }
}
