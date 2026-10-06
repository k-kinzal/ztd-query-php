<?php

declare(strict_types=1);

namespace Deriver;

use Deriver\Project\ProjectSnapshot;
use Deriver\Query\Query;
use Deriver\Reference\Observation;
use Deriver\Reference\ResultRef;
use Deriver\Result\Candidates\CandidateCollection;
use Deriver\Result\Explanation;
use Deriver\Result\ResultSet;

/**
 * An immutable source world with reusable analysis queries and explanations.
 *
 * @visibility public
 * @example Opening an empty source snapshot
 *     $session = (new \Deriver\Analyzer())->open(new \Deriver\Project\ProjectInput([]));
 *     $session->snapshot()->sources // => []
 */
interface AnalysisSession
{
    /**
     * Selects an expression by a half-open byte range and syntactic operation.
     */
    /**
     * Selects an expression by its exact byte range and syntactic role.
     * @throws Exception\InvalidInputException If the range does not select one captured expression
     */
    public function expression(string $path, int $start, int $end, string $role = 'value'): Reference\ExpressionRef;
    /**
     * Provides declaration and signature facts for selecting entries and building models.
     * @return Model\Metadata\DeclarationLookup Captured read-only metadata
     */
    public function declarations(): Model\Metadata\DeclarationLookup;

    /**
     * Reads raw PHPDoc attached to nodes inside a captured callable or script.
     * @param string $symbol Callable or script identity
     * @return list<Reference\SourceComment> Comments with source ranges, without type interpretation
     */
    public function comments(string $symbol): array;

    /**
     * Derives a value, state, return, or correlated tuple.
     * @param Query $query Immutable query
     * @return CandidateCollection Values and quality assessment
     */
    public function derive(Query $query): CandidateCollection;

    /**
     * Shares the snapshot across independent requests.
     * @param list<Query> $queries Independent queries
     * @return ResultSet Results in request order
     */
    public function deriveMany(array $queries): ResultSet;

    /**
     * Shares candidate dependencies across separately budgeted observations, like deriveMany().
     * Under the explicit execution contract, this instead runs one callable with a shared budget:
     * all queries must have the same owner and budget, and results share execution statistics and frontiers.
     * Each tuple preserves its own correlation in either contract.
     * @param list<Query> $queries Queries in output order
     * @return ResultSet Results under the selected analysis contract
     */
    public function deriveTogether(array $queries): ResultSet;

    /**
     * Releases session-owned candidate evaluations and result retention.
     * Results still owned by callers remain immutable and usable.
     */
    public function release(): void;

    /**
     * Retrieves the explanation for a result from this session.
     * Keep the CandidateCollection alive while using its reference: by default only 32 small recent results are retained strongly;
     * older or large results may be released once the caller drops them.
     * @param ResultRef $result Result reference
     * @return list<Result\Evidence\Alternative> Candidate derivations
     */
    public function explain(ResultRef $result): array;

    /**
     * Returns the immutable source and configuration manifest.
     * @return ProjectSnapshot Snapshot metadata
     */
    public function snapshot(): ProjectSnapshot;

    /**
     * Finds source call observations without running the application.
     * A function name selects function calls, and a method name selects method and static calls of that name on any receiver.
     * `Class::__construct` selects `new Class(...)` sites, reported with the `new` operation and the created class as the target;
     * `self` and `parent` resolve to their lexical class, and late-bound `new static` keeps `static` as its target.
     * `*` also selects dynamic function and method calls such as `$f()` and `$object->$method()`;
     * their target is the empty string. Dynamic `new $class` and anonymous classes are not reported.
     * @param string $symbol Function or method name, `Class::__construct`, or `*`
     * @return list<Observation> Source-ordered call observations
     */
    public function callsTo(string $symbol): array;
    /**
     * Returns entries explicitly supplied by registered providers.
     * @return list<Project\EntryPoint> Captured application entries
     */
    public function entrypoints(): array;

    /**
     * Generates named queries from registered observation providers.
     * @return array<string, Query> Ordinary independent public queries
     */
    public function observations(): array;
}
