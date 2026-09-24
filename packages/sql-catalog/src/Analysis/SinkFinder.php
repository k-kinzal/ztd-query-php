<?php

declare(strict_types=1);

namespace SqlCatalog\Analysis;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;
use SqlCatalog\Extension\SinkRole;
use SqlCatalog\Extension\SinkSpec;
use SqlCatalog\Php\ParsedFile;

/**
 * Finds every call that is written the way a database call is written.
 *
 * Reading a statement and finding the call that carries it are separate jobs.
 * A call whose statement cannot be reconstructed is still a place the program
 * talks to a database, and a call nothing could be read from is a gap in the
 * analysis rather than an absence in the program. This pass answers the second
 * question on its own, so neither can be lost to a failure of the first.
 *
 * @visibility root
 */
final class SinkFinder
{
    private NodeFinder $finder;

    /**
     * Builds a finder over the node search.
     */
    public function __construct()
    {
        $this->finder = new NodeFinder();
    }

    /**
     * The calls in a file that are written the way a statement-carrying call is written.
     *
     * @param list<SinkSpec> $sinks
     * @return list<Expr\CallLike>
     */
    public function find(ParsedFile $file, array $sinks): array
    {
        return $this->findIn($file->statements, $this->namesOf($sinks));
    }

    /**
     * The calls in a file written the way any database call is written, including those that only bind values.
     *
     * Calls that compose a statement are left out: they hand the statement back
     * rather than sending it, so they are read as part of whatever call does.
     *
     * @param list<SinkSpec> $sinks
     * @return list<Expr\CallLike>
     */
    public function findAll(ParsedFile $file, array $sinks): array
    {
        $names = [];
        foreach ($sinks as $sink) {
            if ($sink->role !== SinkRole::Compose) {
                $names[strtolower(ltrim($sink->name, '\\'))] = true;
            }
        }

        return $this->findIn($file->statements, $names);
    }

    /**
     * The calls among the given nodes that are written the way a statement-carrying call is written.
     *
     * @param array<array-key, Node> $nodes
     * @param array<string, true> $names
     * @return list<Expr\CallLike>
     */
    public function findIn(array $nodes, array $names): array
    {
        $found = [];
        /** @var list<Expr\CallLike> $calls */
        $calls = $this->finder->findInstanceOf($nodes, Expr\CallLike::class);
        foreach ($calls as $call) {
            $written = $this->nameOf($call);
            if ($written !== null && isset($names[strtolower($written)])) {
                $found[] = $call;
            }
        }

        return $found;
    }

    /**
     * The names the statement-carrying calls are written with.
     *
     * @param list<SinkSpec> $sinks
     * @return array<string, true>
     */
    public function namesOf(array $sinks): array
    {
        $names = [];
        foreach ($sinks as $sink) {
            if ($sink->role === SinkRole::Query || $sink->role === SinkRole::Prepare || $sink->role === SinkRole::Builder) {
                $names[strtolower(ltrim($sink->name, '\\'))] = true;
            }
        }

        return $names;
    }

    /**
     * The name a call is written with, or null when it is not written as a name.
     */
    public function nameOf(Expr\CallLike $call): ?string
    {
        if ($call instanceof Expr\MethodCall || $call instanceof Expr\NullsafeMethodCall) {
            return $call->name instanceof Node\Identifier ? $call->name->toString() : null;
        }
        if ($call instanceof Expr\StaticCall) {
            return $call->name instanceof Node\Identifier ? $call->name->toString() : null;
        }
        if ($call instanceof Expr\FuncCall) {
            return $call->name instanceof Node\Name ? $call->name->toString() : null;
        }

        return null;
    }

    /**
     * The function body a node is written inside, or null when it is written outside any.
     */
    public function enclosingBody(Node $node): ?Node\FunctionLike
    {
        $parent = $node->getAttribute('parent');
        while ($parent instanceof Node) {
            if ($parent instanceof Node\FunctionLike) {
                return $parent;
            }
            $parent = $parent->getAttribute('parent');
        }

        return null;
    }
}
