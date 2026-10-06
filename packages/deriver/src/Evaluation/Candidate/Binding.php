<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Candidate;

/**
 * An actual argument or capture retained without requesting its value.
 * @visibility root
 */
final class Binding
{
    /**
     * @param list<string>|null $registers Lazy variadic arguments
     * @param array{int, int}|null $position Capture position for a storage binding
     */
    public function __construct(public readonly Frame $frame, public readonly string $register, public readonly ?array $registers = null, public readonly ?array $position = null)
    {
    }
    /**
     * Resolves only this actual argument or lexical storage capture.
     */
    public function value(Derivation $engine, string $type, int $depth): \Deriver\Value\Term
    {
        if ($this->position !== null) {
            return (new Storage($engine))->search($this->frame, $this->register, $this->position[0], $this->position[1], $depth);
        }
        if ($this->registers !== null) {
            return \Deriver\Value\Term::array(array_map(fn (string $register): \Deriver\Value\Term => $engine->value($this->frame, $register, $depth), $this->registers));
        }
        $value = $engine->value($this->frame, $this->register, $depth);
        return (new Language\DeclarationCoercion())->check($engine, $value, $type, $this->frame->graph->body->strict);
    }
}
