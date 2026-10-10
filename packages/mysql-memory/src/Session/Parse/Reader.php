<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Parse;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\CacheOptions;
use MySqlMemory\Session\Session;
use MySqlMemory\Session\Syntax;
use SqlParser\Lexer\SourceException;
use SqlParser\Parser\Node;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;

/**
 * Reads the text of a statement as the parser of the server does, and raises the problems it finds there before the statement is resolved.
 *
 * Outside a prepared statement a parameter marker is refused where the parser meets it, and
 * MySQL 5.6 refuses a table named twice before it; a temporal literal is checked as it is read,
 * except in SIGNAL and RESIGNAL, whose literals are checked with their other problems; and SHOW
 * PARSE_TREE is refused last. MySQL 5.6 and 5.7 find an INTO variable no stored program
 * declares, and a table named twice in a nested join, while they parse a statement SQL Semantics
 * refuses later (verified on live 5.6.51, 5.7.44, 8.0.44, 8.4.7 and 9.1.0 servers).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/prepare.html.
 *
 * @visibility MySqlMemory
 */
final class Reader
{
    /**
     * Parses one statement and raises the problems the parser of the server finds in it, recording on the session the leading dots and the hint comments it warns about.
     *
     * @param bool $prepared Whether the statement is prepared, where parameter markers are allowed
     *
     * @throws SqlError When the statement does not parse or the parser refuses it
     */
    public function read(string $statement, bool $prepared, Session $session): Node
    {
        $semantics = $session->semantics();
        try {
            $tree = $semantics->parser()->parse($statement);
        } catch (SourceException $error) {
            throw (!$prepared ? (new Syntax())->premature($error, $statement, $semantics->parser(), $session->following) : null) ?? (new Syntax())->error($error, $statement, $session->settings()->release(), $session->following);
        }
        if (!$prepared) {
            $listed = $session->settings()->release() === GrammarRelease::MySql5651 ? (new Clashes())->listed($tree, $session->variables->database) : null;
            if ($listed !== null) {
                throw (new CacheOptions())->clashed($listed[0], $listed[1], $statement, $session);
            }
            (new Syntax())->markers($tree, $statement, $session->following);
        }
        if (!(new \MySqlMemory\Command\Condition\SignalProblems())->signals($tree)) {
            (new Syntax())->temporals($tree, $session->modes(), $session, $statement);
        }
        $session->dots = $session->settings()->release() === GrammarRelease::MySql5744 ? (new Syntax())->dots($tree) : [];
        $session->hinted = (new \MySqlMemory\Hint\Hints())->syntax($tree, $statement, $session);
        $session->commented = str_contains($statement, '/*+');
        (new Syntax())->debugOnly($tree, $statement);

        return $tree;
    }

    /**
     * Answers the error of a statement SQL Semantics refuses: in MySQL 5.6 and 5.7 an INTO variable no stored program declares, then a table named twice, which they find while they parse it; otherwise the parse error of the refusal.
     *
     * @param string $database The current database
     */
    public function refusal(Node $tree, AnalysisException $error, string $statement, string $database, Session $session): SqlError
    {
        $release = $session->settings()->release();
        $legacy = in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true);
        $clash = $legacy ? ((new Clashes())->targets($tree, $error, $database) ?? (new Clashes())->nested($tree, $error, $database)) : null;
        $undeclared = $legacy ? (new Syntax())->undeclared($tree, $error, $session->program) : null;

        return ($undeclared === null ? null : (new CacheOptions())->undeclared($undeclared, $statement, $session)) ?? ($clash === null ? null : (new CacheOptions())->clashed($clash[0], $clash[1], $statement, $session)) ?? (new Syntax())->error($error, $statement, $release);
    }

    /**
     * Records the error of a text whose first statement does not parse, and answers it.
     *
     * A parameter marker outside a prepared statement is refused where the parser meets it, before a
     * later syntax error (verified on live 5.6.51, 5.7.44, 8.0.44, 8.4.7 and 9.1.0 servers).
     */
    public function refused(SqlError $error, string $sql, bool $prepared, Session $session): SqlError
    {
        $session->diagnostics->clear();
        $error = (!$prepared ? (new Syntax())->premature($error, $sql, $session->semantics()->parser()) : null) ?? $error;
        $error = (new CacheOptions())->reported($error, $sql, $session);
        if (!$error->recorded) {
            $session->diagnostics->error($error->getCode(), $error->getMessage());
        }
        $session->variables->rowCount = -1;

        return $error;
    }

    /**
     * Answers the type of the value bound to each parameter marker of a statement, by the position of the marker.
     *
     * A statement prepared before any value is bound types each marker as the server types a lone
     * marker then: a VARCHAR of 16383 characters in the connection collation.
     *
     * @param list<array{int|float|string|null, \MySqlMemory\Typing\Domain}> $parameters The values bound in the order of the markers
     * @return array<int, Domain>
     */
    public function bound(Node $tree, array $parameters, bool $prepared, Session $session): array
    {
        $bound = [];
        $index = 0;
        $unbound = Domain::string(16383, $session->resolution()->connection, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::VarString, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility::Coercible);
        foreach ($tree->tokens() as $token) {
            if ($token->name !== 'PARAM_MARKER') {
                continue;
            }
            if (isset($parameters[$index])) {
                $bound[$index] = ($session->preparation->domains[$index] ?? $parameters[$index][1])->resolved();
            } elseif ($prepared) {
                $bound[$index] = $unbound;
            }
            $index++;
        }

        return $bound;
    }
}
