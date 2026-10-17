<?php

declare(strict_types=1);

namespace MySqlMemory\Program\Routine;

use MySqlMemory\Dictionary\Routine;
use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Name\CollationName;

/**
 * Reports collation, administrative-option and function-name notices when a session first loads a routine definition.
 *
 * A routine DDL invalidates these session entries; table flushes do not. The definition
 * supplies typed collation occurrences and function names, without reparsing stored display text.
 * Loading a definition with notices replaces preceding conditions from MySQL 5.7 onward;
 * a silent load leaves them intact. In MySQL 5.6 loading OLD_PASSWORD discards
 * preceding conditions without publishing the suppressed load warning; subsequent
 * cached calls retain them. Other load notices accumulate within the statement.
 * Observed with repeated calls, ALTER PROCEDURE and independent connections on MySQL 5.6–9.1.
 *
 * @visibility MySqlMemory
 */
final class LoadNotices
{
    /**
     * Loads a user definition's notices once per session and dictionary generation.
     */
    public function record(Routine $routine, Session $session): void
    {
        $prepared = $session->preparation;
        $generation = $session->instance->dictionary->routineGeneration;
        if ($prepared->routineGeneration !== $generation) {
            $prepared->routineGeneration = $generation;
            $prepared->routines = [];
        }
        $identity = spl_object_id($routine);
        if (isset($prepared->routines[$identity]) || $routine->statement === null) {
            return;
        }
        $prepared->routines[$identity] = true;
        if ($this->discards($routine, $session)) {
            $session->diagnostics->clear();
        }
        $stale = $session->diagnostics->stale;
        if ($session->settings()->release() !== \SqlSemantics\Contract\GrammarRelease::MySql5651) {
            $session->diagnostics->stale = true;
        }
        foreach ((new Walker())->find($routine->statement, \SqlSemantics\Statement\Node::class) as $node) {
            $notice = $this->notice($node, $session->settings()->release());
            if ($notice !== null) {
                $session->diagnostics->warning($notice->code(), $notice->message());
            }
        }
        if ($routine->statement instanceof \SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction && $routine->statement->nameCollision($session->settings()->release())) {
            $code = \MySqlMemory\Error\Family\AdministrationError::NativeFunctionName;
            $session->diagnostics->note($code, $code->message($routine->name));
        }
        if ($session->diagnostics->stale) {
            $session->diagnostics->stale = $stale;
        }
    }
    /**
     * Reads load-time notices from typed occurrences in their written order.
     * The utf8 alias warns again on loading; ucs2 and utf8mb3 deprecations do not.
     */
    public function notice(\SqlSemantics\Statement\Node $node, \SqlSemantics\Contract\GrammarRelease $release): ?\SqlSemantics\Platform\MySql\Statement\Notice\Deprecation
    {
        if ($node instanceof CollationName) {
            return $node->deprecation($release);
        }
        if ($node instanceof \SqlSemantics\Platform\MySql\Statement\Server\Flush\FlushItem) {
            $notice = $node->deprecation($release);
            return $notice === null ? null : new \SqlSemantics\Platform\MySql\Statement\Notice\Deprecation($notice);
        }
        $name = match (true) {
            $node instanceof \SqlSemantics\Platform\MySql\Statement\Name\CharsetName => $node->name,
            $node instanceof \SqlSemantics\Platform\MySql\Statement\Type\CharsetAttribute => $node->charset,
            $node instanceof \SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral,
            $node instanceof \SqlSemantics\Platform\MySql\Statement\Literal\RadixLiteral => $node->introducer,
            default => null,
        };
        $notice = \SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::Utf8Alias;
        return strtolower($name->value ?? '') === 'utf8' && $notice->warnedIn($release) ? new \SqlSemantics\Platform\MySql\Statement\Notice\Deprecation($notice) : null;
    }
    /**
     * Recognizes the MySQL 5.6 load whose suppressed notice replaces prior conditions.
     */
    public function discards(Routine $routine, Session $session): bool
    {
        if ($session->settings()->release() !== \SqlSemantics\Contract\GrammarRelease::MySql5651 || $routine->statement === null) {
            return false;
        }
        $calls = [...(new Walker())->find($routine->statement, \SqlSemantics\Platform\MySql\Statement\Call\KeywordCall::class), ...(new Walker())->find($routine->statement, \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredKeywordCall::class)];
        foreach ($calls as $call) {
            if ($call->function === \SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::OldPassword) {
                return true;
            }
        }
        return false;
    }

}
