<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Rules\Manipulation\CopyFacts;
use SqlSemantics\Platform\PostgreSql\Rules\Query\AliasSpelling;
use SqlSemantics\Platform\PostgreSql\Rules\Query\ClosedList;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\TargetTable;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * COPY between a table and a file, a program or the client.
 *
 * Mirrors PostgreSQL's `CopyStmt` with a `relation` (relation, attlist,
 * is_from, is_program, filename, options, whereClause). The options are
 * those of the old syntax, written one after another, or the generic ones
 * in parentheses; BINARY after COPY and DELIMITERS are old-syntax options
 * too. The facts follow PG-COPY-001.
 * Source: https://www.postgresql.org/docs/17/sql-copy.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading a COPY of a table
 *     $copy = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze("COPY t (a) FROM PROGRAM 'cat x' WITH (format csv) WHERE a > 0");
 *     [$copy->statement->program, $copy->statement->file->value, $copy->toString()] // => [true, 'cat x', "COPY t (a) FROM PROGRAM 'cat x' (format csv) WHERE a > 0"]
 * @example Refusing both kinds of options
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy\CopyTable(false, new \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\TargetTable(new \SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')))), [], \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy\CopyDirection::To, false, \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy\CopyStream::Stdout, null, [\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy\CopyFlag::Csv], [new \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Copy\CopyOption(new \SqlSemantics\Statement\Identifier\Name('header'))]) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class CopyTable implements Statement
{
    use Snapshot;

    /**
     * @var list<Name> The columns copied, in written order; none is every column
     */
    public readonly array $columns;

    /**
     * @var list<CopyFlag|CopyText|CopyForce> The options of the old syntax in written order
     */
    public readonly array $legacy;

    /**
     * @var list<CopyOption> The parenthesized options in written order
     */
    public readonly array $options;

    /**
     * @param bool $binary Whether BINARY is written after COPY
     * @param TargetTable $table The table; never with ONLY or an alias
     * @param list<Name> $columns The columns copied, in written order
     * @param CopyDirection $direction FROM or TO
     * @param bool $program Whether the file is a program
     * @param StringConstant|CopyStream $file The file name or command, or the client
     * @param StringConstant|null $delimiters The string of the old DELIMITERS option
     * @param list<CopyFlag|CopyText|CopyForce> $legacy The options of the old syntax
     * @param list<CopyOption> $options The parenthesized options
     * @param Scalar|null $where The condition rows must meet to be copied
     *
     * @throws InvalidConstruction When the table has ONLY or an alias, both kinds of options are written, or a list holds a foreign item
     */
    public function __construct(
        public readonly bool $binary,
        public readonly TargetTable $table,
        array $columns,
        public readonly CopyDirection $direction,
        public readonly bool $program,
        public readonly StringConstant|CopyStream $file,
        public readonly ?StringConstant $delimiters = null,
        array $legacy = [],
        array $options = [],
        public readonly ?Scalar $where = null,
    ) {
        Check::input(!$table->table->only && $table->alias === null, 'COPY names a table without ONLY and alias.');
        $this->columns = Check::listOf($columns, Name::class, 'The column list of COPY holds names.');
        $this->legacy = (new ClosedList())->of($legacy, [CopyFlag::class, CopyText::class, CopyForce::class], 'The old COPY options are keyword, string and FORCE options.');
        $this->options = Check::listOf($options, CopyOption::class, 'The parenthesized COPY options are options.');
        Check::input($this->legacy === [] || $this->options === [], 'COPY takes the options of the old syntax or parenthesized options, not both.');
    }

    /**
     * Derives the table, the columns, the options and the condition.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new CopyFacts())->table($this, $derivation);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('COPY');
        if ($this->binary) {
            $out->keyword('BINARY');
        }
        $out->node($this->table);
        (new AliasSpelling())->names($out, $this->columns);
        $out->keyword($this->direction->value);
        if ($this->program) {
            $out->keyword('PROGRAM');
        }
        $out->node($this->file);
        if ($this->delimiters !== null) {
            $out->keyword('DELIMITERS')->node($this->delimiters);
        }
        foreach ($this->legacy as $item) {
            $out->node($item);
        }
        if ($this->options !== []) {
            $out->symbol('(')->list($this->options)->symbol(')');
        }
        if ($this->where !== null) {
            $out->keyword('WHERE')->node($this->where);
        }
    }
}
