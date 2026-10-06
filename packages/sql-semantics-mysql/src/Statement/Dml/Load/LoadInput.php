<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Load;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * What LOAD reads: `[LOCAL] INFILE|URL|S3 'name' [COUNT n] [IN PRIMARY KEY ORDER]`.
 *
 * LOCAL makes the client read the file. COUNT and IN PRIMARY KEY ORDER
 * belong to the bulk load of the HeatWave grammars (8.0.30 and later) and
 * tell how many files the name pattern matches and that they are sorted by
 * primary key. The 8.4 and later grammars read the word COUNT as an
 * identifier, which the server requires to be `count` in any letter case;
 * that word is kept so it is written back the way the grammar reads it.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/load-data.html.
 *
 * @visibility public
 * @example Reading the input of LOAD DATA
 *     $load = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("LOAD DATA LOCAL INFILE 'f.csv' INTO TABLE t");
 *     [$load->statement->input->local, $load->statement->input->source, $load->statement->input->file->value] // => [true, \SqlSemantics\Platform\MySql\Statement\Dml\Load\LoadSource::Infile, 'f.csv']
 */
final class LoadInput implements Node
{
    use Snapshot;

    /**
     * @param bool $local Whether the client reads the file
     * @param LoadSource $source The kind of location
     * @param Text $file The file name, URL or pattern
     * @param Numeral|null $count The number of files
     * @param bool $primaryKeyOrder Whether the files are sorted by primary key
     * @param Name|null $countWord The word COUNT when the grammar reads it as an identifier (8.4 and later), which may be quoted; null for the keyword
     * @throws InvalidConstruction When the word COUNT is given without a count or is another word
     */
    public function __construct(
        public readonly bool $local,
        public readonly LoadSource $source,
        public readonly Text $file,
        public readonly ?Numeral $count = null,
        public readonly bool $primaryKeyOrder = false,
        public readonly ?Name $countWord = null,
    ) {
        Check::input($countWord === null || ($count !== null && strcasecmp($countWord->value, 'count') === 0), 'The word before the number of files is COUNT.');
    }

    /**
     * Writes the input.
     */
    public function render(Output $out): void
    {
        if ($this->local) {
            $out->keyword('LOCAL');
        }
        $out->keyword($this->source->value)->node($this->file);
        if ($this->countWord !== null) {
            $out->name($this->countWord, NameUse::Label);
        } elseif ($this->count !== null) {
            $out->keyword('COUNT');
        }
        $out->node($this->count);
        if ($this->primaryKeyOrder) {
            $out->keyword('IN', 'PRIMARY', 'KEY', 'ORDER');
        }
    }
}
