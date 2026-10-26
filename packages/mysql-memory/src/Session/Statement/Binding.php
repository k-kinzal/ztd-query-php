<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Statement;

use MySqlMemory\Hint\Hints;
use MySqlMemory\Session\Resolution;
use MySqlMemory\Session\Session;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Contract\Platforms;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;
use SqlSemantics\Statement\AnalyzedStatement;
use SqlSemantics\Statement\Operation;
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
    public function operation(Session $session, Statement $statement, array $parameters = [], ?LanguageProfile $profile = null, ?Settings $literals = null): Operation
    {
        return new Operation($this->context($session, $statement, $parameters, $profile, $literals), $statement);
    }

    /**
     * Publishes a bound statement through the dialect's concrete-statement boundary.
     *
     * @param list<array{int|float|string|null, \MySqlMemory\Typing\Domain}> $parameters Bound values and their domains
     */
    public function statement(Session $session, Statement $statement, array $parameters = [], ?LanguageProfile $profile = null, ?Settings $literals = null, SourceMap $sources = new SourceMap()): AnalyzedStatement
    {
        if ($statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Transaction\TransactionStatement || $statement instanceof \SqlSemantics\Platform\MySql\Statement\Account\RenameUser || $statement instanceof \SqlSemantics\Platform\MySql\Statement\Account\CreateRole || $statement instanceof \SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantRoles) {
            \SqlSemantics\Diagnostic\Check::input($profile === null || $statement->profile()->compatibleWith($profile), 'A catalog-independent statement retains its selected language when reused.');
            return $statement;
        }
        return Platforms::of('mysql')->publish($this->context($session, $statement, $parameters, $profile, $literals), $statement, $sources);
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
            $session->instance->dictionary->temporaries = $session->temporaries;
            Resolution\Views::refreshAll($session->instance->dictionary, $session->settings());
            $profile ??= $session->semantics()->profile();
            $database = $session->variables->database;
            $context = Platforms::of('mysql')->context($profile, $database === '' ? null : [$database], $session->instance->dictionary->declarations(), true);
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
