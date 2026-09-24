<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Procedural;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Loading\BulkLoadStatement;
use SqlSemantics\Model\Statement\Loading\LoadFileStatement;
use SqlSemantics\Model\Statement\Loading\LoadFormat;
use SqlSemantics\Model\Statement\Loading\LoadLayout;
use SqlSemantics\Model\Statement\Loading\LoadSource;
use SqlSemantics\Serialization\Expressions;
use SqlSemantics\Serialization\Query\Relations;
use SqlSemantics\Serialization\Write\Assignments;

/**
 * Writes LOAD DATA and LOAD XML from their operands in the clause order every MySQL release accepts.
 * @visibility SqlSemantics
 */
final class Loads
{
    /**
     * Spells the source keyword only when it is not INFILE, so row loads stay readable by MySQL 5.x.
     */
    public static function write(LoadFileStatement|BulkLoadStatement $statement): Tree
    {
        $bulk = $statement instanceof BulkLoadStatement;
        $format = $bulk ? LoadFormat::Data : $statement->format;
        $head = [Build::keyword('LOAD ' . $format->value)];
        if (!$bulk && $statement->scheduling !== null) {
            $head[] = Build::keyword($statement->scheduling->value);
        }
        if (!$bulk && $statement->local) {
            $head[] = Build::keyword('LOCAL');
        }
        $head[] = Build::keyword($statement->location === LoadSource::File ? 'INFILE' : ($bulk ? 'FROM ' : '') . $statement->location->value);
        $head[] = Expressions::write($statement->file);
        if ($bulk && $statement->fileCount !== null) {
            $head[] = Build::keyword('COUNT ' . $statement->fileCount);
        }
        if ($bulk && $statement->keyOrdered) {
            $head[] = Build::keyword('IN PRIMARY KEY ORDER');
        }
        if ($statement->duplicates !== null) {
            $head[] = Build::keyword($statement->duplicates->value);
        }
        $head[] = Build::keyword('INTO TABLE');
        $head[] = Relations::target($statement->table, Dialect::MySql);
        if ($statement->partitions !== null) {
            $head[] = Build::keyword('PARTITION');
            $head[] = Build::parentheses(Build::separated(array_map(static fn (string $name): Tree => Build::identifier([$name], Dialect::MySql), $statement->partitions->names)));
        }
        return new Tree('load', [...$head, ...self::layout($statement, $format), ...($bulk ? self::bulk($statement) : self::rows($statement))]);
    }

    /**
     * Writes the character set, compression, separators and skipped rows; an XML row terminator is spelled as ROWS IDENTIFIED BY.
     * @return list<Tree>
     */
    public static function layout(LoadFileStatement|BulkLoadStatement $statement, LoadFormat $format): array
    {
        $layout = $statement->layout;
        $parts = $layout->characterSet === null ? [] : [Build::keyword('CHARACTER SET'), Build::identifier([$layout->characterSet], Dialect::MySql)];
        if ($statement instanceof BulkLoadStatement && $statement->compression !== null) {
            array_push($parts, Build::keyword('COMPRESSION ='), Expressions::write($statement->compression));
        }
        $terminator = $layout->lines->terminator;
        if ($format === LoadFormat::Xml && $terminator !== null) {
            array_push($parts, Build::keyword('ROWS IDENTIFIED BY'), Expressions::write($terminator));
            $terminator = null;
        }
        return [...$parts, ...self::separators($layout, $terminator), ...($layout->skippedRows === 0 ? [] : [Build::keyword('IGNORE ' . $layout->skippedRows . ' LINES')])];
    }

    /**
     * Writes the FIELDS and LINES clauses with only the separators the statement sets.
     * @return list<Tree>
     */
    public static function separators(LoadLayout $layout, ?\SqlSemantics\Model\Scalar\Value\Literal $terminator): array
    {
        $fields = $layout->fields;
        $parts = [];
        foreach (['TERMINATED BY' => $fields->terminator, ($fields->optionallyEnclosed ? 'OPTIONALLY ' : '') . 'ENCLOSED BY' => $fields->enclosure, 'ESCAPED BY' => $fields->escape] as $keyword => $separator) {
            if ($separator !== null) {
                array_push($parts, Build::keyword($keyword), Expressions::write($separator));
            }
        }
        $lines = [];
        foreach (['STARTING BY' => $layout->lines->start, 'TERMINATED BY' => $terminator] as $keyword => $separator) {
            if ($separator !== null) {
                array_push($lines, Build::keyword($keyword), Expressions::write($separator));
            }
        }
        return [...($parts === [] ? [] : [Build::keyword('FIELDS'), ...$parts]), ...($lines === [] ? [] : [Build::keyword('LINES'), ...$lines])];
    }

    /**
     * Writes the receiving columns and variables and the SET items of a row load.
     * @return list<Tree>
     */
    public static function rows(LoadFileStatement $statement): array
    {
        $parts = $statement->targets === [] ? [] : [Build::parentheses(Build::separated(array_map(Expressions::write(...), $statement->targets)))];
        return $statement->assignments === [] ? $parts : [...$parts, Build::keyword('SET'), Assignments::write($statement->assignments)];
    }

    /**
     * Writes the bulk loader options after ALGORITHM-independent clauses.
     * @return list<Tree>
     */
    public static function bulk(BulkLoadStatement $statement): array
    {
        $parts = $statement->parallel === null ? [] : [Build::keyword('PARALLEL = ' . $statement->parallel)];
        if ($statement->memory !== null) {
            $parts[] = Build::keyword('MEMORY = ' . $statement->memory);
        }
        return [...$parts, Build::keyword('ALGORITHM = BULK')];
    }
}
