<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Model\Facade;

use SqlParser\Parser\SqlParser;
use SqlSemantics\AnalysisContext as Context;
use SqlSemantics\Platform\Sqlite\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\Sqlite\Model\Contract\LanguageProfile;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Platform;

/**
 * Builds dialect-owned binding contexts from relation, index and trigger definitions.
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
        $indexes = [];
        $triggers = [];
        $opaqueIndexSchemas = [];
        foreach ($declarations ?? [] as $sql) {
            $scope = $platform->context($this->profile, $this->searchPath, $tables, false);
            $binding = $binder->resolve($sql, $scope);
            $trigger = $platform->triggerDeclaration($binding, $complete);
            if ($trigger !== null) {
                $triggers[] = $trigger;
                continue;
            }
            $index = $platform->indexDeclaration($binding);
            if ($index !== null) {
                $indexes[] = $index;
                continue;
            }
            Check::input($binding->facts->declarations !== [], 'A catalog definition must declare a relation, an index or a trigger.');
            array_push($tables, ...$platform->declarations($binding));
            $constraints = $platform->constraintIndexes($binding);
            if ($constraints === null) {
                foreach ($binding->facts->declarations as $owner) {
                    $opaqueIndexSchemas[] = $owner->name->schema ?? $scope->declarationSchema;
                }
            } else {
                array_push($indexes, ...$constraints);
            }
        }

        return $platform->context(
            $this->profile,
            $this->searchPath,
            $tables,
            $declarations !== null && $complete,
            $indexes,
            $opaqueIndexSchemas,
            $triggers,
        );
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

        return $context;
    }
}
