<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Session;
use MySqlMemory\Session\Syntax;
use SqlParser\Parser\Node;
use SqlParser\Parser\ReductionParser;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Lowering\SyntaxWarnings;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;

/**
 * Keeps grammar conditions ahead of modern temporal-literal interpretation.
 *
 * Modern releases finish reading grammar operands before interpreting temporal
 * text. A grammar refusal is left to ordinary semantic analysis so its other
 * conditions are preserved. Otherwise all completed syntax warnings precede
 * the temporal error, including warnings written after that literal.
 * MySQL 5.6 interprets literals during parsing; 5.7 still reports them before
 * contextual collation lookup, but after completed CAST precision checks.
 * Observed on native MySQL 5.6.51, 5.7.44, 8.0.44, 8.4.7 and 9.1.0.
 *
 * @visibility MySqlMemory
 */
final class TemporalInput
{
    /**
     * Checks literal text without publishing the input tree or source text.
     *
     * @throws SqlError When the first temporal refusal follows successful grammar interpretation
     */
    public function check(Node $tree, string $statement, int $end, Session $session): void
    {
        $contextual = [];
        try {
            (new Syntax(Charset::named((string) $session->variables->read('character_set_client'))))->temporals($tree, $session->modes(), $session, $statement, $end, $contextual);
        } catch (SqlError $failure) {
            $parser = $session->semantics()->parser();
            Check::invariant($parser instanceof ReductionParser, 'The MySQL parser reports completed productions.');
            $notices = array_values(array_filter($this->notices($parser, $statement, $session->settings()->legacy() ? $end : strlen($statement), $session), static fn (array $notice): bool => !$notice[1] instanceof ParseFailure || $notice[1]->phase === \SqlSemantics\Platform\MySql\Statement\Notice\NoticePhase::Syntax));
            foreach ($notices as [, $notice]) {
                if ($notice instanceof ParseFailure && !$notice->problem instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Problem\RefusedTemporalLiteral && !$session->settings()->legacy()) {
                    throw (new ModernWarnings())->recorded(array_column($notices, 1), $failure, $session, false);
                }
            }
            if ($session->settings()->legacy()) {
                $literal = $session->settings()->release() === GrammarRelease::MySql5651 ? $this->before($statement, $end, $session) : null;
                foreach ($notices as [$offset, $notice]) {
                    if ($notice instanceof ParseFailure && ($notice->problem instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Problem\TooBigPrecision || $notice->problem instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Problem\RefusedNamesAssignment) && ($literal === null || $offset < $literal[0])) {
                        throw (new ModernWarnings())->recorded(array_column($notices, 1), $failure, $session, false);
                    }
                }
                throw $failure;
            }
            throw (new ModernWarnings())->recorded([...array_column($notices, 1), ...$contextual], $failure, $session);
        }
    }

    /**
     * Interprets a decoded temporal value under the current runtime modes and zone.
     */
    public function literal(\SqlSemantics\Platform\MySql\Statement\Literal\TemporalLiteral|\SqlSemantics\Platform\MySql\Statement\Expression\Problem\RefusedTemporalLiteral $literal, \MySqlMemory\Session\SqlModes $modes, ?Session $session = null): ?SqlError
    {
        $form = $literal->form === \SqlSemantics\Platform\MySql\Statement\Literal\TemporalForm::Timestamp ? 'DATETIME' : $literal->form->value;
        $text = $literal->text;
        $value = $form === 'DATETIME'
            ? \MySqlMemory\Evaluation\Compile\Family\TimestampLiteral::value($text, 6, $modes, (\MySqlMemory\Value\Zone::named((string) ($session?->variables->read('time_zone') ?? 'SYSTEM'), $modes->release) ?? \MySqlMemory\Value\Zone::utc()))
            : \MySqlMemory\Value\Temporal::literal($form, $text, 6, $modes->has('NO_ZERO_DATE'), $modes->has('NO_ZERO_IN_DATE'), $modes->has('ALLOW_INVALID_DATES'));
        if ($value !== null) {
            return null;
        }
        return $form === 'DATETIME' ? \MySqlMemory\Evaluation\Compile\Family\TimestampLiteral::error($text, $modes) : \MySqlMemory\Error\Family\DataError::WrongValue->error($form, substr($text, 0, 128));
    }

    /**
     * Resolves a decoded variable read before a later temporal value is interpreted.
     * Legacy releases defer scope misuse until ordinary expression preparation.
     *
     * @param array<int, \SqlSemantics\Platform\MySql\Statement\Notice\Deprecation> $notices Contextual warnings reached before a later refusal
     */
    public function variable(\SqlSemantics\Platform\MySql\Statement\Variable\SystemVariable|\SqlSemantics\Platform\MySql\Statement\Variable\UserVariable $variable, Session $session, array &$notices = [], bool $view = false, int $offset = 0): ?SqlError
    {
        if ($view) {
            return \MySqlMemory\Error\Family\SchemaError::ViewSelectVariable->error();
        }
        if ($variable instanceof \SqlSemantics\Platform\MySql\Statement\Variable\UserVariable) {
            return null;
        }
        $derivation = new \SqlSemantics\Construction\Derivation($session->semantics()->context([], session: $session->resolution()));
        $variable->deriveScalar($derivation, $derivation->environment());
        foreach ($derivation->facts()->warnings as $warning) {
            Check::invariant($warning instanceof \SqlSemantics\Platform\MySql\Statement\Notice\Deprecation, 'System-variable warnings describe deprecated names.');
            $notices[$offset] = $warning;
        }
        foreach ($derivation->facts()->diagnostics as $problem) {
            if (!$session->settings()->legacy() || !$problem instanceof \SqlSemantics\Platform\MySql\Statement\Variable\Problem\VariableMisuse) {
                return (new \MySqlMemory\Session\Problem\Errors())->error($problem, $session);
            }
        }
        return null;
    }

    /**
     * Combines semantic input conditions with accepted operator warnings in source order.
     *
     * @return list<array{int, \SqlSemantics\Platform\MySql\Statement\Notice\Deprecated|\SqlSemantics\Platform\MySql\Statement\Notice\Deprecation|\SqlSemantics\Platform\MySql\Statement\Notice\NameConversion|\SqlSemantics\Platform\MySql\Statement\Notice\HeartbeatPeriod|ParseFailure|\SqlSemantics\Platform\MySql\Statement\Notice\ParseStop}>
     */
    public function notices(ReductionParser $parser, string $statement, int $end, Session $session): array
    {
        $notices = (new SyntaxWarnings())->completed($parser, $statement, $end, $session->semantics()->profile(), $session->resolution()->replicaNetTimeout, $session->resolution()->client);
        if (!$session->settings()->legacy()) {
            foreach ((new ModernWarnings())->before($parser->tokenize($statement), $statement, $end, $parser) as $offset => $notice) {
                $notices[] = [$offset, $notice];
            }
        }
        usort($notices, static fn (array $left, array $right): int => $left[0] <=> $right[0]);

        return $notices;
    }

    /**
     * Interprets a completed legacy value before a later grammar refusal.
     *
     * @throws SqlError When a completed temporal or variable operand is refused
     */
    public function legacy(Node $node, Session $session): void
    {
        $client = Charset::named((string) $session->variables->read('character_set_client'));
        if ($node->name === 'temporal_literal') {
            (new Syntax($client))->temporals($node, $session->modes());
            return;
        }
        foreach ((new \SqlSemantics\Platform\MySql\Lowering\Expression\ContextualReading())->read($node, $session->semantics()->profile(), $client) as [, , $value]) {
            if ($value instanceof \SqlSemantics\Platform\MySql\Statement\Variable\SystemVariable) {
                $failure = $this->variable($value, $session);
                if ($failure !== null) {
                    throw $failure;
                }
            }
        }
    }

    /**
     * Finds a literal refusal interpreted before a later syntax failure.
     *
     * MySQL 5.6 interprets individual literals during parsing. MySQL 5.7 also
     * interprets a completed client statement before a following syntax failure.
     * Later releases interpret stored-program members when they complete.
     *
     * @return array{int, SqlError}|null The interpretation boundary and its value error
     */
    public function before(string $statement, int $end, Session $session): ?array
    {
        $legacy = $session->settings()->release() === GrammarRelease::MySql5651;
        $kinds = $legacy ? ['temporal_literal', 'variable_aux'] : ($session->settings()->legacy() ? ['statement', 'sp_proc_stmt', 'ev_sql_stmt_inner'] : ['sp_proc_stmt', 'ev_sql_stmt_inner']);
        $parser = $session->semantics()->parser();
        Check::invariant($parser instanceof ReductionParser, 'The MySQL parser reports completed productions.');
        $found = null;
        try {
            $parser->parse($statement, static function (Node $node, \SqlParser\Lexer\Token $lookahead) use ($end, $session, $legacy, $statement, $kinds, &$found): void {
                if ($found !== null || !in_array($node->name, $kinds, true) || $lookahead->offset > $end) {
                    return;
                }
                try {
                    if ($legacy) {
                        (new self())->legacy($node, $session);
                    } else {
                        (new self())->check($node, $statement, $lookahead->offset, $session);
                    }
                } catch (SqlError $failure) {
                    $found = [$legacy ? $node->tokens()[0]->offset : $lookahead->offset, $failure];
                }
            });
        } catch (\SqlParser\Lexer\SourceException) {
            // The caller keeps the original syntax refusal if no earlier literal failed.
        }

        return $found;
    }
}
