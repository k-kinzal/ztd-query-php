<?php

declare(strict_types=1);

namespace Deriver\Analysis\Selection;

use Deriver\ControlFlow\CallableIdentity;
use Deriver\Exception\InvalidInputException;
use Deriver\Project\ProjectInput;
use Deriver\Query;
use Deriver\Query\ParameterQuery;
use Deriver\Query\Query as AnalysisQuery;
use Deriver\Query\ReturnQuery;
use Deriver\Query\ValueQuery;
use Deriver\Reference\Observation;
use Deriver\Source\Declaration\ProjectIndex;

/**
 * Resolves selector fields against the captured source before deriving candidates.
 * @visibility root
 */
final class SelectorResolution
{
    /**
     * @param ProjectIndex $program Captured declarations and lazy graphs
     */
    public function __construct(public readonly ProjectIndex $program)
    {
    }

    /**
     * Binds a return, formal parameter, or source call argument to the analysis request.
     * @throws InvalidInputException If the selector is unsupported or its target is absent
     */
    public function resolve(Query $query): AnalysisQuery
    {
        if ($query->line === null && $query->selector === 'return' && $query->arguments === []) {
            if ($this->program->callable($query->target) === null) {
                throw new InvalidInputException('Return target is not a captured callable: ' . $query->target);
            }
            return new ReturnQuery($query->target);
        }
        if ($query->line === null && $query->selector === 'parameter' && count($query->arguments) === 1 && is_string($query->arguments[0])) {
            return new ParameterQuery($query->target, $query->arguments[0]);
        }
        if ($query->line !== null && $query->selector === 'argument' && count($query->arguments) === 1) {
            return new ValueQuery($this->callAt($query)->argument($query->arguments[0]));
        }
        throw new InvalidInputException('Supported selectors are callable#return, callable#parameter(name), and path:line:column#argument(index-or-name).');
    }

    /**
     * Selects the call beginning at the exact line and byte column in a captured file.
     * Relative paths are resolved against the working directory, as in Analyzer::analyze().
     * @throws InvalidInputException If no unique source call matches the selector
     */
    public function callAt(Query $query): Observation
    {
        $cwd = getcwd();
        if ($cwd === false) {
            throw new InvalidInputException('Cannot resolve the current directory for a source selector.');
        }
        $absolute = static function (string $path) use ($cwd): string {
            $path = str_replace('\\', '/', $path);
            return ProjectInput::normalize(str_starts_with($path, '/') || preg_match('~\A[A-Za-z]:/~', $path) === 1 ? $path : $cwd . '/' . $path);
        };
        $path = $absolute($query->target);
        $observations = new CallObservations($this->program);
        $matches = [];
        foreach ($this->program->callOwners('*') as $owner) {
            $declaration = $this->program->declarations[(new CallableIdentity())->key($owner)] ?? null;
            if ($declaration === null || $absolute($declaration->path) !== $path) {
                continue;
            }
            $callable = $this->program->callable($owner);
            if ($callable === null) {
                continue;
            }
            foreach ($observations->within($callable, '*') as $observation) {
                if ($absolute($observation->source->path) === $path && $observation->source->line === $query->line && $observation->source->column === $query->column) {
                    $matches[] = $observation;
                }
            }
        }
        if (count($matches) !== 1) {
            throw new InvalidInputException('Source selector must identify exactly one captured call: ' . $query->toString());
        }
        return $matches[0];
    }
}
