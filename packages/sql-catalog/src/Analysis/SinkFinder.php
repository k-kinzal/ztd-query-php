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
 * talks to a database, and a call the walk never reached is a gap in the
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
     * The bodies that can reach a database call, keyed by where they are written.
     *
     * Walking a function that cannot reach a database call learns nothing: the
     * analysis starts at the call that receives SQL and works back from there.
     * A body qualifies when it writes such a call itself, or when it calls
     * something that does. Matching callees by their written name
     * over-approximates, which keeps bodies in rather than dropping them.
     *
     * @param list<ParsedFile> $files
     * @param list<SinkSpec> $sinks
     * @return array<string, true>
     */
    public function reaching(array $files, array $sinks): array
    {
        $names = $this->namesOf($sinks);
        $bodies = [];
        foreach ($files as $file) {
            foreach ($this->bodiesOf($file) as $key => $body) {
                $bodies[$key] = ['direct' => false, 'calls' => [], 'declares' => $this->declaredName($body)];
            }
            foreach ($this->finder->findInstanceOf($file->statements, Expr\CallLike::class) as $call) {
                $written = $this->nameOf($call);
                $key = $this->bodyKeyOf($file, $call);
                if ($written === null || !isset($bodies[$key])) {
                    continue;
                }
                $bodies[$key]['calls'][] = strtolower($this->shortName($written));
                $bodies[$key]['direct'] = $bodies[$key]['direct'] || isset($names[strtolower($written)]);
            }
        }

        return $this->propagate($bodies);
    }

    /**
     * Every body of a file, keyed by where it is written, with the file itself under `main`.
     *
     * @return array<string, Node\FunctionLike|null>
     */
    public function bodiesOf(ParsedFile $file): array
    {
        $bodies = [$file->path . ':main' => null];
        foreach ($this->finder->findInstanceOf($file->statements, Node\FunctionLike::class) as $body) {
            $bodies[$file->path . ':' . $body->getStartFilePos()] = $body;
        }

        return $bodies;
    }

    /**
     * The key of the body a node is written in.
     */
    public function bodyKeyOf(ParsedFile $file, Node $node): string
    {
        $body = $this->enclosingBody($node);

        return $file->path . ':' . ($body === null ? 'main' : $body->getStartFilePos());
    }

    /**
     * The name a body is declared under, or null when it is not declared under one.
     */
    public function declaredName(?Node\FunctionLike $body): ?string
    {
        if ($body instanceof Node\Stmt\ClassMethod || $body instanceof Node\Stmt\Function_) {
            return strtolower($body->name->toString());
        }

        return null;
    }

    /**
     * A name without the namespace it is written in.
     */
    public function shortName(string $written): string
    {
        $parts = explode('\\', ltrim($written, '\\'));

        return $parts[count($parts) - 1];
    }

    /**
     * The bodies that reach a database call, once reaching has spread through the calls.
     *
     * @param array<string, array{direct: bool, calls: list<string>, declares: string|null}> $bodies
     * @return array<string, true>
     */
    public function propagate(array $bodies): array
    {
        $reaching = [];
        $reachingNames = [];
        foreach ($bodies as $key => $body) {
            if ($body['direct']) {
                $reaching[$key] = true;
                $reachingNames[$body['declares'] ?? ''] = true;
            }
        }

        $changed = true;
        while ($changed) {
            $changed = false;
            foreach ($bodies as $key => $body) {
                if (isset($reaching[$key]) || array_intersect_key($reachingNames, array_flip($body['calls'])) === []) {
                    continue;
                }
                $reaching[$key] = true;
                $reachingNames[$body['declares'] ?? ''] = true;
                $changed = true;
            }
        }

        return $reaching;
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
            if ($sink->role === SinkRole::Query || $sink->role === SinkRole::Prepare) {
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
