<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Statement;

use MySqlMemory\Hint\Hints;
use MySqlMemory\Session\Session;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Contract\Platforms;
use SqlSemantics\Platform\MySql\Statement\Statement as MySqlStatement;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;
use SqlSemantics\Statement\AnalyzedStatement;
use SqlSemantics\Statement\Source\SourceMap;
use SqlSemantics\Statement\Statement;

/**
 * Resolves an existing semantic statement against current declarations and bindings.
 *
 * A prepared statement supplies its original lexical profile and literal encoding.
 * Neither source SQL nor a parser tree is an input to this boundary.
 *
 * @visibility MySqlMemory
 */
final class Binding
{
    /**
     * Reuses the original structure, refreshing its table and parameter facts.
     *
     * @param list<array{int|float|string|null, \MySqlMemory\Typing\Domain}> $parameters Bound values and their domains
     */
    public function operation(Session $session, Statement $statement, array $parameters = [], ?LanguageProfile $profile = null, ?Settings $literals = null): MySqlStatement
    {
        $result = (new \SqlSemantics\Platform\MySql\Construction\Publication())->publish($this->context($session, $statement, $parameters, $profile, $literals), $statement, new SourceMap());
        if ($statement instanceof MySqlStatement && isset($session->preparation->programSources[$statement])) {
            $session->preparation->programSources[$result] = $session->preparation->programSources[$statement];
        }
        return $result;
    }

    /**
     * Publishes a bound statement through the dialect's concrete-statement boundary.
     *
     * @param list<array{int|float|string|null, \MySqlMemory\Typing\Domain}> $parameters Bound values and their domains
     */
    public function statement(Session $session, Statement $statement, array $parameters = [], ?LanguageProfile $profile = null, ?Settings $literals = null, SourceMap $sources = new SourceMap()): AnalyzedStatement
    {
        $result = Platforms::of('mysql')->publish($this->context($session, $statement, $parameters, $profile, $literals), $statement, $sources);
        \SqlSemantics\Diagnostic\Check::invariant($result instanceof MySqlStatement, 'MySQL binding publishes a MySQL statement.');
        if ($statement instanceof MySqlStatement && isset($session->preparation->programSources[$statement])) {
            $session->preparation->programSources[$result] = $session->preparation->programSources[$statement];
        }
        return $result;
    }

    /**
     * Captures the declaration and parameter inputs while typing hints apply.
     *
     * @param list<array{int|float|string|null, \MySqlMemory\Typing\Domain}> $parameters Bound values and their domains
     */
    public function context(Session $session, Statement $statement, array $parameters, ?LanguageProfile $profile, ?Settings $literals): AnalysisContext
    {
        $typing = (new Hints())->typingValue($statement, $session);
        try {
            $profile ??= $session->semantics()->profile();
            $database = $session->variables->database;
            $context = Platforms::of('mysql')->context($profile, $database === '' ? null : [$database], Catalog::declarations($session), true);
            return $context->withSession($session->resolution($this->parameters($session, $parameters), $literals));
        } finally {
            $typing->restore($session);
        }
    }

    /**
     * Resolves bound marker domains, preserving legacy NULL metadata and derived types.
     *
     * @param list<array{int|float|string|null, \MySqlMemory\Typing\Domain}> $parameters Bound values and their domains
     * @return array<int, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain>
     */
    public function parameters(Session $session, array $parameters): array
    {
        $domains = [];
        foreach ($parameters as $position => [$value, $domain]) {
            $domains[$position] = $session->replayed && $session->settings()->legacy() && $value === null
                ? new \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::Null, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::VarString, coercibility: \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility::Coercible)
                : ($session->preparation->domains[$position] ?? $domain)->resolved();
        }
        return $domains;
    }

}
