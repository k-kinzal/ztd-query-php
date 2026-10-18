<?php

declare(strict_types=1);

namespace Deriver\Deriver\Selection;

use Deriver\Deriver\Evaluator\Request\Observation\Observation;
use Deriver\Deriver\Evaluator\Request\ParameterQuery;
use Deriver\Deriver\Evaluator\Request\Query as AnalysisQuery;
use Deriver\Deriver\Evaluator\Request\ReturnQuery;
use Deriver\Deriver\Evaluator\Request\ValueQuery;
use Deriver\Deriver\Lowerer\Declaration\CallableIdentity;
use Deriver\Deriver\Lowerer\Declaration\Index;
use Deriver\Deriver\Query;
use Deriver\Deriver\Sources\Path;
use Deriver\Exception\InvalidInputException;

/**
 * Resolves selector fields against the captured source before deriving candidates.
 * @visibility root
 */
final class SelectorResolution
{
    private readonly string $directory;

    /**
     * @param Index $program Captured declarations and lazy graphs
     * @throws InvalidInputException If the source base directory cannot be determined
     */
    public function __construct(private readonly Index $program)
    {
        $directory = getcwd();
        if ($directory === false) {
            throw new InvalidInputException('Cannot resolve the current directory for source selectors.');
        }
        $this->directory = $directory;
    }

    /**
     * Binds a return, formal parameter, or source call argument to the analysis request.
     * @throws InvalidInputException If the selector is unsupported or its target is absent
     */
    public function resolve(Query $query): AnalysisQuery
    {
        $scope = $query->path === [] ? null : \Deriver\Deriver\Evaluator\Request\QueryScope::through(new \Deriver\Deriver\Evaluator\Request\CallPath($query->path));
        foreach ($query->path as $caller) {
            if ($caller !== '...' && $this->program->callable($caller) === null) {
                throw new InvalidInputException('Call path target is not a captured callable: ' . $caller);
            }
        }
        if ($query->line === null && $query->selector === 'return' && $query->arguments === []) {
            if ($this->program->callable($query->target) === null) {
                throw new InvalidInputException('Return target is not a captured callable: ' . $query->target);
            }
            return new ReturnQuery($query->target, $scope);
        }
        if ($query->line === null && $query->selector === 'parameter' && count($query->arguments) === 1 && is_string($query->arguments[0])) {
            return new ParameterQuery($query->target, $query->arguments[0], $scope);
        }
        if ($query->line !== null && $query->selector === 'argument' && count($query->arguments) === 1) {
            $scope = $query->path === [] ? null : \Deriver\Deriver\Evaluator\Request\QueryScope::through(new \Deriver\Deriver\Evaluator\Request\CallPath($query->path, true));
            return new ValueQuery($this->callAt($query)->argument($query->arguments[0]), scope: $scope);
        }
        throw new InvalidInputException('Supported selectors are callable#return, callable#parameter(name), and path:line:column#argument(index-or-name).');
    }

    /**
     * Selects the call beginning at the exact line and byte column in a captured file.
     * Relative paths are resolved against the working directory captured during preparation.
     * @throws InvalidInputException If no unique source call matches the selector
     */
    public function callAt(Query $query): Observation
    {
        $cwd = $this->directory;
        $absolute = static function (string $path) use ($cwd): string {
            $path = str_replace('\\', '/', $path);
            return Path::normalize(str_starts_with($path, '/') || preg_match('~\A[A-Za-z]:/~', $path) === 1 ? $path : $cwd . '/' . $path);
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
