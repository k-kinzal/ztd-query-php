<?php

declare(strict_types=1);

namespace SqlCatalog\Core\Php;

use PhpParser\Node;
use PhpParser\Node\Stmt;

/**
 * What the global variables a body declares are known to hold.
 *
 * `global $wpdb;` leaves nothing in the statement for the analyzer to read a
 * type from, and the handle an application hands out that way is often the one
 * every statement it issues goes through. Losing it loses the statements with
 * it, so two things are allowed to say what the name stands for: the `@global`
 * or `@var` tag documenting the declaration, which is how an application says
 * it in its own source, and an extension, which is how a framework's handle is
 * known without the application having said anything.
 *
 * @visibility root
 */
final class DeclaredGlobals
{
    /**
     * @var array<string, string>
     */
    private array $declared;

    /**
     * @param array<string, string> $declared Class names, keyed by the variable name written without its `$`
     */
    public function __construct(array $declared = [])
    {
        $this->declared = $declared;
    }

    /**
     * The class a declared global holds, or null when nothing says what it holds.
     */
    public function classOf(Stmt\Global_ $statement, string $name): ?string
    {
        return $this->documented($statement, $name) ?? $this->declared[$name] ?? null;
    }

    /**
     * The class an extension declares a global to hold, or null when none does.
     *
     * Code at the top of a file reads globals without declaring them, so
     * there is no `global` statement for a tag to be written on; what an
     * extension says is all there is to go on.
     */
    public function declared(string $name): ?string
    {
        return $this->declared[$name] ?? null;
    }

    /**
     * The class a doc comment around the declaration gives the variable, or null when none does.
     */
    public function documented(Stmt\Global_ $statement, string $name): ?string
    {
        foreach ($this->comments($statement) as $comment) {
            $className = $this->tagged($comment, $name);
            if ($className !== null) {
                return $className;
            }
        }

        return null;
    }

    /**
     * The doc comments that can document the declaration, nearest first.
     *
     * The tag naming the type is written on the declaration the `global`
     * statement is in far more often than on the statement itself, so the
     * search climbs to the enclosing function and stops there: beyond it the
     * comment documents something else.
     *
     * @return list<string>
     */
    public function comments(Stmt\Global_ $statement): array
    {
        $comments = [];
        $own = $statement->getDocComment();
        if ($own !== null) {
            $comments[] = $own->getText();
        }

        $node = $statement->getAttribute('parent');
        while ($node instanceof Node) {
            $comment = $node->getDocComment();
            if ($comment !== null) {
                $comments[] = $comment->getText();
            }
            if ($node instanceof Node\FunctionLike) {
                break;
            }
            $node = $node->getAttribute('parent');
        }

        return $comments;
    }

    /**
     * The class an `@global` or `@var` tag gives the variable, or null when neither does.
     */
    public function tagged(string $comment, string $name): ?string
    {
        $pattern = '/@(?:global|var)\s+([\\\\\w|]+)\s+\$' . preg_quote($name, '/') . '\b/';
        if (preg_match($pattern, $comment, $matches) !== 1) {
            return null;
        }
        $written = ltrim(explode('|', $matches[1])[0], '\\');

        return $written === '' ? null : $written;
    }
}
