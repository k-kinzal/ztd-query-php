<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use ZtdQuery\Platform\ParameterBindingCompiler;

/**
 * A parameter binding compiler that answers one fixed result and remembers being asked.
 */
final class RecordingParameterBindingCompiler implements ParameterBindingCompiler
{
    /**
     * Every call, in the order it arrived.
     *
     * @var list<array{string, array<int|string, mixed>|null}>
     */
    public array $calls = [];

    /**
     * The answer every call gets. Public because the parameters a driver binds
     * are whatever the caller passed, so the shape carries the interface's own
     * mixed and only a public boundary may state one.
     *
     * @var array{sql: string, params: array<int|string, mixed>|null}
     */
    public readonly array $answer;

    /**
     * Binds the compiler to what it answers.
     *
     * @param array{sql: string, params: array<int|string, mixed>|null} $answer What every call answers with
     */
    public function __construct(array $answer)
    {
        $this->answer = $answer;
    }

    /**
     * Answers the fixed result, and remembers the statement and parameters it was given.
     *
     * @param string $sql Statement to compile
     * @param array<int|string, mixed>|null $params Parameters to compile against it
     *
     * @return array{sql: string, params: array<int|string, mixed>|null} The answer this compiler was built with
     */
    public function compile(string $sql, ?array $params): array
    {
        $this->calls[] = [$sql, $params];

        return $this->answer;
    }
}
