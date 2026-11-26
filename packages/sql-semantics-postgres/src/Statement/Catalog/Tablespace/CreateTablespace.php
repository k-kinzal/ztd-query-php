<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Tablespace;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Statement;
use SqlSemantics\Platform\PostgreSql\Rules\Catalog\ClauseFacts;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpec;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Definition;

/**
 * A request to create a tablespace.
 *
 * Rule: PG-TABLESPACE-001. Mirrors `CreateTableSpaceStmt`: name, owner,
 * location and the tablespace parameters of WITH ( ... ).
 * Source: https://www.postgresql.org/docs/17/sql-createtablespace.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class CreateTablespace implements Statement
{
    use Snapshot;

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
    public function __construct(public readonly Name $name, public readonly ?RoleSpec $owner, public readonly StringConstant $location, array $options = [])
    {
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
