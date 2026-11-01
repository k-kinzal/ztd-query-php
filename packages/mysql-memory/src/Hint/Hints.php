<?php

declare(strict_types=1);

namespace MySqlMemory\Hint;

use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\KeyHint;
use SqlSemantics\Statement\Node;
use WeakMap;

/**
 * Handles the optimizer hints of the statements a session runs, at the three moments the server reads them.
 *
 * While the statement is parsed, a problem in a hint comment is warning 1064 (syntax). Before
 * the statement opens its tables, the hints of its query blocks are read (Registration) and
 * applied (Application). When the tables are set up, the names the hints give are resolved
 * (Resolution). MySQL 5.6 has no hints; 5.7 has fewer than 8.0 (HintName::available()). The
 * hints change no result: join order, index, join buffering and subquery strategy hints are
 * read and checked only, MAX_EXECUTION_TIME sets no timer, and RESOURCE_GROUP binds no thread.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/optimizer-hints.html.
 *
 * @visibility MySqlMemory
 */
final class Hints
{
    /**
     * The variables SET_VAR sets that change how SQL Semantics types a statement, which the server reads after it applies the hints.
     */
    public const TYPING = ['div_precision_increment', 'group_concat_max_len'];



    /**
     * Applies typing variables from the semantic hint values before direct construction
     * derives expression domains. No input text or parser state is consulted.
     */
    public function typing(Node $statement, Session $session): Application
    {
        if (!$this->hinted($session)) {
            return new Application();
        }
        $registration = $this->registration($statement, $session);
        $registration->variables = array_intersect_key($registration->variables, array_fill_keys(self::TYPING, true));
        $registration->group = null;
        $registration->warnings = [];
        $application = (new Application())->apply($registration, $session);
        $application->warnings = [];

        return $application;
    }

    /**
     * Reads the hints of a statement and applies those that count to the session; the warnings are raised later, by Application::report().
     */
    public function apply(Node $statement, Session $session, bool $preparing = false): Application
    {
        if (!$this->hinted($session)) {
            return new Application();
        }

        if ($statement instanceof \SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure || $statement instanceof \SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction || $statement instanceof \SqlSemantics\Platform\MySql\Statement\Routine\CreateTrigger) {
            $application = new Application();
            $application->warnings = (new ProgramHints())->warnings($statement, $session);
            return $application;
        }
        return (new Application())->apply($this->registration($statement, $session), $session, $session->replayed || $session->program !== null, $preparing);
    }

    /**
     * Prepares a typed operation with ordered hint conditions; the variables keep their values.
     *
     * @throws \MySqlMemory\Error\SqlError When preparation rejects the operation
     */
    public function prepare(\SqlSemantics\Statement\AnalyzedStatement $operation, Session $session): void
    {
        (new \MySqlMemory\Session\Access\Preparation())->check($operation, $session);
    }

    /**
     * Raises the warnings about the tables and indexes the hints of a statement name that its query blocks do not have.
     */
    public function resolve(Node $statement, Session $session): void
    {
        if (!$this->hinted($session) || $session->replayed) {
            return;
        }
        foreach ((new Resolution($this->registration($statement, $session), new Printer($session->modes()->has('ANSI_QUOTES'))))->resolve()->warnings as [$code, $message]) {
            $session->diagnostics->warning($code, $message);
        }
    }

    /**
     * Reads the index-level hints a view keeps, INDEX, JOIN_INDEX, GROUP_INDEX, ORDER_INDEX and their NO_ forms, with their warnings; answers the comment SHOW CREATE VIEW writes after the first SELECT of the view.
     *
     * The server reads no other hint of a view, QB_NAME included, and keeps those whose table it
     * finds, each naming its table with the block, in the order they were read (verified on a
     * live 8.4 server).
     */
    public function view(\SqlSemantics\Statement\Query $query, Session $session): string
    {
        if (!$this->hinted($session)) {
            return '';
        }
        $blocks = new Blocks($session->instance->dictionary, $session->variables->database, $session->temporaries);
        $blocks->resolved = $blocks->query($query, false);
        foreach ($blocks->blocks as $block) {
            $block->hints = array_values(array_filter($block->hints, static fn ($hint): bool => $hint instanceof KeyHint && in_array(Registration::kind($hint->hint), Registration::FAMILY, true)));
        }
        $printer = new Printer($session->modes()->has('ANSI_QUOTES'));
        $registration = (new Registration($blocks, $session->variables->catalog, $printer))->register();
        foreach ([...$registration->warnings, ...(new Resolution($registration, $printer))->resolve()->warnings] as [$code, $message]) {
            $session->diagnostics->warning($code, $message);
        }
        $texts = [];
        foreach ($registration->accepted as [$number, $hint, $table]) {
            if ($hint instanceof KeyHint && $table !== null && $blocks->blocks[$number]->table($table->name) !== null) {
                $texts[] = $hint->hint->value . '(' . (new Printer())->quote($table->name) . '@' . (new Printer())->quote('select#' . $number) . ($hint->indexes === [] ? '' : ' ' . implode(', ', array_map((new Printer())->quote(...), $hint->indexes))) . ')';
            }
        }

        return $texts === [] ? '' : '/*+ ' . implode(' ', $texts) . ' */ ';
    }

    /**
     * Reads the hints of the query blocks of a statement.
     */
    public function registration(Node $statement, Session $session): Registration
    {
        $blocks = (new Blocks($session->instance->dictionary, $session->variables->database, $session->temporaries))->read($statement);

        return (new Registration($blocks, $session->variables->catalog, new Printer($session->modes()->has('ANSI_QUOTES')), $session->program !== null))->register();
    }

    /**
     * Tells whether this execution context reads typed hints. Stored functions and triggers ignore them, and MySQL 5.6 has no optimizer hints. Input text does not participate in this decision.
     */
    public function hinted(Session $session): bool
    {
        return $session->program?->contained !== true && $session->settings()->release() !== \SqlSemantics\Contract\GrammarRelease::MySql5651;
    }
    /**
     * Captures input-only hint spellings for later diagnostic display.
     */
    public function messages(\SqlSemantics\Platform\MySql\Statement\Statement $statement, string $input, Session $session): void
    {
        foreach ($statement->statementInformation()->warnings as $warning) {
            if ($warning instanceof \SqlSemantics\Platform\MySql\Statement\Hint\Comment\HintError) {
                $session->preparation->hintMessages ??= new WeakMap();
                $session->preparation->hintMessages[$warning] = $warning->message($input);
            }
        }
    }

    /**
     * Emits hint warnings at their leading or trailing position in the input.
     *
     * @param array<int, \SqlSemantics\Platform\MySql\Statement\Hint\Comment\HintError> $hints Semantic hint conditions
     */
    public function warnings(array $hints, bool $leading, Session $session): void
    {
        foreach ($hints as $hint) {
            if ($hint->leading === $leading) {
                $session->diagnostics->warning($hint->code(), $session->preparation->hintMessages[$hint] ?? $hint->message());
            }
        }
    }

}
