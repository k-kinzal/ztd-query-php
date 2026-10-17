<?php

declare(strict_types=1);

namespace Deriver\Project;

use Deriver\Reference\ExpressionRef;
use Deriver\Reference\Observation;
use Deriver\Reference\SourceComment;

/**
 * Read access to a captured project: its snapshot, declarations, comments, call sites and entry points.
 * A session exposes this view to callers, and providers read it to shape their contributions.
 *
 * @visibility public
 * @example Reading the captured project through a session
 *     $session = (new \Deriver\Session\Session(new \Deriver\Project\ProjectInput([]), new \Deriver\Project\Configuration()));
 *     $session->callsTo('missing') // => []
 */
interface CapturedProject
{
    /**
     * Selects an expression by its exact byte range and syntactic role.
     * @throws \Deriver\Exception\InvalidInputException If the range does not select one captured expression
     */
    public function expression(string $path, int $start, int $end, string $role = 'value'): ExpressionRef;

    /**
     * Provides declaration and signature facts for selecting entries and building models.
     * @return Declaration\DeclarationLookup Captured read-only metadata
     */
    public function declarations(): Declaration\DeclarationLookup;

    /**
     * Reads raw PHPDoc attached to nodes inside a captured callable or script.
     * @param string $symbol Callable or script identity
     * @return list<SourceComment> Comments with source ranges, without type interpretation
     */
    public function comments(string $symbol): array;

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
     * @return list<EntryPoint> Captured application entries
     */
    public function entrypoints(): array;
}
