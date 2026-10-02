<?php

declare(strict_types=1);

namespace Deriver;

use Deriver\Project\ProjectSnapshot;
use Deriver\Query\Query;
use Deriver\Reference\Observation;
use Deriver\Reference\ResultRef;
use Deriver\Result\DerivationResult;
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
     * Provides declaration and signature facts for selecting entries and building models.
     * @return Model\Metadata\DeclarationLookup Captured read-only metadata
     */
    public function declarations(): Model\Metadata\DeclarationLookup;

    /**
     * Derives a value, state, return, or correlated tuple.
     * @param Query $query Immutable query
     * @return DerivationResult Values and quality assessment
     */
    public function derive(Query $query): DerivationResult;

    /**
     * Shares the snapshot across independent requests.
     * @param list<Query> $queries Independent queries
     * @return ResultSet Results in request order
     */
    public function deriveMany(array $queries): ResultSet;

    /**
     * Retrieves the explanation for a result from this session.
     * @param ResultRef $result Result reference
     * @return Explanation Derivations, assumptions, and boundaries
     */
    public function explain(ResultRef $result): Explanation;

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
     * `*` selects every call and creation whose name is written in the source; calls through dynamic names such as `$f()`
     * or `new $class`, and anonymous classes, are not reported.
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
