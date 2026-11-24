<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql;

use SqlParser\Parser\SqlParser;
use SqlSemantics\AnalysisContext as Context;
use SqlSemantics\Platform\PostgreSql\Lowering\Catalog\CatalogIndexes;
use SqlSemantics\Platform\PostgreSql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\PostgreSql\Model\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Facade\Index\CatalogRelationCoordinates;
use SqlSemantics\Platform\PostgreSql\Model\Facade\StatementBinder;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\CreateRole;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Schema\CreateSchema;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Tablespace\CreateTablespace;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Index\CreateIndex;

/**
 * Builds dialect-owned binding contexts from relation definitions.
 *
 * @visibility SqlSemantics
 */
final class Catalog
{
    /**
     * @param list<string>|null $searchPath
     */
    public function __construct(
        private readonly LanguageProfile $profile,
        private readonly ?array $searchPath,
        private readonly SqlParser $parser,
        private readonly ?Name $database = null,
    ) {
    }

    /**
     * Collects declarations without executing their statements.
     * @param list<string>|null $declarations
     */
    public function build(?array $declarations, bool $complete): AnalysisContext
    {
        $platform = Platform::shared();
        $binder = new StatementBinder($this->parser);
        $tables = [];
        $tablespaces = [];
        $roles = [];
        $indexes = new CatalogIndexes();
        $coordinates = new CatalogRelationCoordinates();
        $closed = $declarations !== null && $complete;
        foreach ($declarations ?? [] as $sql) {
            $scope = $platform->context($this->profile, $this->searchPath, $tables, $closed, $indexes->snapshot(), $this->database, $tablespaces, $roles);
            $binding = $binder->resolve($sql, $scope);
            if ($binding->statement instanceof CreateRole) {
                $roles[] = $binding->statement->name;

                continue;
            }
            if ($binding->statement instanceof CreateTablespace) {
                $tablespaces[] = $binding->statement->name;

                continue;
            }
            Check::input(
                $binding->facts->declarations !== [] || $binding->statement instanceof CreateSchema || $binding->statement instanceof CreateIndex,
                'A catalog definition must declare a relation, index, schema, tablespace or role.',
            );
            $indexes->add($binding);
            $indexes->addIndexes($binding);
            foreach ($binding->facts->declarations as $table) {
                $tables[] = $coordinates->absolute($table, $scope->declarationSchema);
            }
        }

        return $platform->context($this->profile, $this->searchPath, $tables, $closed, $indexes->snapshot(), $this->database, $tablespaces, $roles);
    }

    /**
     * Verifies the dialect and profile of the context supplied by the caller.
     */
    public function resolve(?Context $context): AnalysisContext
    {
        if ($context === null) {
            return $this->build(null, false);
        }
        Check::input($context instanceof AnalysisContext, 'The context must belong to the selected database.');
        Check::input($this->profile->compatibleWith($context->profile), 'The context must match the selected language profile.');
        Check::input($this->database === null || $context->currentDatabase?->value === $this->database->value, 'The context must match the selected current database.');

        return $context;
    }

}
