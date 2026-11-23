<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Model\Facade;

use SqlParser\Parser\SqlParser;
use SqlSemantics\AnalysisContext as Context;
use SqlSemantics\Platform\PostgreSql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\PostgreSql\Model\Contract\LanguageProfile;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Platform;

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
        $closed = $declarations !== null && $complete;
        foreach ($declarations ?? [] as $sql) {
            $scope = $platform->context($this->profile, $this->searchPath, $tables, $closed);
            $binding = $binder->resolve($sql, $scope);
            Check::input($binding->facts->declarations !== [], 'A catalog definition must declare a relation.');
            array_push($tables, ...$binding->facts->declarations);
        }

        return $platform->context($this->profile, $this->searchPath, $tables, $closed);
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
