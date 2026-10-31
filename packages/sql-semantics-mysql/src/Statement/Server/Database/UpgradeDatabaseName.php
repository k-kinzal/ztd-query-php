<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Database;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

/**
 * `ALTER DATABASE name UPGRADE DATA DIRECTORY NAME`: a request to rename a database directory to the encoding of MySQL 5.1 (MySQL 5.6 and 5.7).
 *
 * Mirrors SQLCOM_ALTER_DB_UPGRADE. Rule: MYSQL-UPGRADE-DATABASE-001. The
 * name is that of the database as written after the old `#mysql50#` prefix.
 * Raw names remain available when execution refuses the directory identity. Preparing the
 * request does not validate the directory. The statement changes no declaration.
 * Source: https://docs.oracle.com/cd/E17952_01/mysql-5.7-en/alter-database.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Upgrading a database directory name
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, 'mysql-5.7.44'))->analyze('alter database `#mysql50#a-b` upgrade data directory name')->toString() // => 'ALTER DATABASE `#mysql50#a-b` UPGRADE DATA DIRECTORY NAME'
 */
final class UpgradeDatabaseName extends \SqlSemantics\Platform\MySql\Statement\SqlStatement implements Statement, \SqlSemantics\Statement\AnalyzedStatement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param LegacyDatabaseName|Name $name A legacy directory identity or the raw requested name
     */
    public function __construct(public readonly LegacyDatabaseName|Name $name, ?\SqlSemantics\Platform\MySql\Statement\StatementSemantics $statementInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null, public readonly DatabaseWord $databaseWord = DatabaseWord::Database)
    {
        if ($statementInformation !== null) {
            $this->initializeStatementInformation($statementInformation);
        }
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Records the release's deprecation warning; a database is no relation.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        if ($this->name instanceof LegacyDatabaseName) {
            $this->name->derive($derivation);
        } elseif ($this->name->value !== LegacyDatabaseName::converted($this->name->value)) {
            $derivation->warn(new \SqlSemantics\Platform\MySql\Statement\Notice\NameConversion($this->name, true), $this);
        }
        \SqlSemantics\Platform\MySql\Statement\Notice\Deprecation::raise(\SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::UpgradeDatabaseName, $derivation);
    }

    /**
     * Answers the system-character-set identifier used during execution.
     */
    public function identifier(): Name
    {
        return $this->name instanceof LegacyDatabaseName ? $this->name->identifier() : new Name(LegacyDatabaseName::converted($this->name->value), $this->name->quote);
    }

    /**
     * Retains a directory-domain refusal until the request executes.
     */
    public function condition(): ?Problem\InvalidUpgradeName
    {
        $name = $this->identifier();
        return !str_starts_with($name->value, '#mysql50#') || !LegacyDatabaseName::valid(substr($name->value, 9)) ? new Problem\InvalidUpgradeName($name) : null;
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER', $this->databaseWord->value);
        if ($this->name instanceof LegacyDatabaseName) {
            $out->node($this->name);
        } else {
            $out->name($this->name, NameUse::Qualifier);
        }
        $out->keyword('UPGRADE', 'DATA', 'DIRECTORY', 'NAME');
    }
}
