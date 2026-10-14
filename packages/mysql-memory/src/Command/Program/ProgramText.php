<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Program;

use MySqlMemory\Session\Session;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Contract\Platforms;
use SqlSemantics\Platform\MySql\Statement\Routine\ParameterList;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Operation;

/**
 * Supplies display text for stored definitions independently of their executable structure.
 *
 * A lowered definition can preserve its input spelling. A directly constructed definition
 * has no input location and renders its typed operands instead, without parsing session text.
 * The executable routine and trigger bodies remain the supplied semantic nodes in both cases.
 *
 * @visibility MySqlMemory
 */
final class ProgramText
{
    /**
     * @param LanguageProfile $profile The language of the definition
     * @param ProgramSource|null $source The optional input spelling of a lowered definition
     * @param bool $followed Whether the client packet contains another statement after this definition
     */
    public function __construct(public readonly LanguageProfile $profile, public readonly ?ProgramSource $source = null, public readonly bool $followed = false)
    {
    }

    /**
     * Uses input spelling only when the operation records the definition's input location.
     */
    public static function of(Operation $operation, Session $session): self
    {
        return new self($operation->profile(), $operation->sources->of($operation->statement) === null ? null : ProgramSource::of($session), trim($session->following) !== '');
    }

    /**
     * Displays a routine parameter list without its enclosing parentheses.
     */
    public function parameters(ParameterList $parameters, bool $function): string
    {
        return $this->source?->between($function ? 'sf_tail' : 'sp_tail') ?? substr($this->render($parameters), 1, -1);
    }

    /**
     * Displays a routine body, retaining its terminator only before another packet statement.
     */
    public function routineBody(Node $body): string
    {
        if ($this->source === null) {
            return $this->render($body);
        }

        $body = $this->source->body($this->source->tree->find('stored_routine_body') === [] ? 'sp_proc_stmt' : 'stored_routine_body');

        return $this->followed ? $body : rtrim(rtrim($body, ';'));
    }

    /**
     * Displays a trigger or event body, retaining its original spelling when available.
     */
    public function body(Node $body, string $production): string
    {
        return $this->source?->body($production) ?? $this->render($body);
    }

    /**
     * Renders display metadata directly from a semantic operand.
     */
    public function render(Node $node): string
    {
        $output = new Output(Platforms::of($this->profile->grammar->database())->codec($this->profile));
        $node->render($output);

        return (new Lexical())->join($output->pieces()) . $output->trailing();
    }
}
