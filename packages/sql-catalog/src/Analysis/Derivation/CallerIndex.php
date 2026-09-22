<?php

declare(strict_types=1);

namespace SqlCatalog\Analysis\Derivation;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;
use SqlCatalog\Php\ParsedFile;

/**
 * Every call in the source, by the name it is written with.
 *
 * Going from a parameter to the arguments passed for it means finding the
 * calls that reach the function. The calls are gathered once, by name, so
 * that finding them is a lookup; deciding which of them really reach the
 * function is left to whoever asks, because that can need their receivers.
 *
 * @visibility root
 */
final class CallerIndex
{
    /**
     * @var array<string, list<Expr\CallLike>>
     */
    private array $calls = [];

    /**
     * @var array<string, list<Expr\New_>>
     */
    private array $instantiations = [];

    /**
     * Gathers the calls of every file.
     *
     * @param list<ParsedFile> $files
     */
    public function __construct(array $files = [])
    {
        $finder = new NodeFinder();
        foreach ($files as $file) {
            /** @var list<Expr\CallLike> $calls */
            $calls = $finder->findInstanceOf($file->statements, Expr\CallLike::class);
            foreach ($calls as $call) {
                $this->add($call);
            }
        }
    }

    /**
     * Files one call under the name it is written with.
     */
    public function add(Expr\CallLike $call): void
    {
        if ($call instanceof Expr\New_) {
            if ($call->class instanceof Node\Name) {
                $this->instantiations[$this->shortName($call->class->toString())][] = $call;
            }

            return;
        }
        $name = $this->nameOf($call);
        if ($name !== null) {
            $this->calls[$this->shortName($name)][] = $call;
        }
    }

    /**
     * The calls written with that name, whatever they are called on.
     *
     * @return list<Expr\CallLike>
     */
    public function named(string $name): array
    {
        return $this->calls[$this->shortName($name)] ?? [];
    }

    /**
     * The instantiations written with that class name.
     *
     * @return list<Expr\New_>
     */
    public function instantiating(string $className): array
    {
        return $this->instantiations[$this->shortName($className)] ?? [];
    }

    /**
     * The name a call is written with, or null when it is not written as a name.
     */
    public function nameOf(Expr\CallLike $call): ?string
    {
        if ($call instanceof Expr\FuncCall) {
            return $call->name instanceof Node\Name ? $call->name->toString() : null;
        }
        if ($call instanceof Expr\MethodCall || $call instanceof Expr\NullsafeMethodCall || $call instanceof Expr\StaticCall) {
            return $call->name instanceof Node\Identifier ? $call->name->toString() : null;
        }

        return null;
    }

    /**
     * A name without its namespace, in lower case.
     */
    public function shortName(string $name): string
    {
        $parts = explode('\\', ltrim($name, '\\'));

        return strtolower($parts[count($parts) - 1]);
    }
}
