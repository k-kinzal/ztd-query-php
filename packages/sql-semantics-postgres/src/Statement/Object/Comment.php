<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Object;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Routine\ObjectFacts;
use SqlSemantics\Platform\PostgreSql\Rules\Routine\ObjectForms;
use SqlSemantics\Platform\PostgreSql\Rules\Routine\ObjectSpelling;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectKind;
use SqlSemantics\Platform\PostgreSql\Statement\Name\ObjectReference;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `COMMENT ON kind object IS 'text' | NULL`: sets or removes the comment of an object.
 *
 * Mirrors PostgreSQL's `CommentStmt`. NULL, like an empty string, removes the
 * comment. A commented relation or column is resolved.
 * Source: https://www.postgresql.org/docs/17/sql-comment.html.
 *
 * @visibility public
 * @example Reading a comment on a column
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze("COMMENT ON COLUMN t.a IS 'key'");
 *     [$operation->statement->kind->value, $operation->statement->comment?->value] // => ['COLUMN', 'key']
 */
final class Comment implements Statement
{
    use Snapshot;

    /**
     * @param ObjectKind $kind The kind of the object
     * @param ObjectReference $object The object, named as COMMENT names objects of the kind
     * @param StringConstant|null $comment The comment; null for NULL
     */
    public function __construct(public readonly ObjectKind $kind, public readonly ObjectReference $object, public readonly ?StringConstant $comment)
    {
        $forms = new ObjectForms();
        Check::input(in_array($forms->form($object), $forms->comment($kind), true), 'COMMENT names the object as the grammar names objects of its kind.');
    }

    /**
     * Derives the object.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new ObjectFacts())->derive($this->kind, [$this->object], false, $derivation);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('COMMENT', 'ON');
        (new ObjectSpelling())->kind($out, $this->kind);
        $out->node($this->object)->keyword('IS');
        if ($this->comment === null) {
            $out->keyword('NULL');

            return;
        }
        $out->node($this->comment);
    }
}
