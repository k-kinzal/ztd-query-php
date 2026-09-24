<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\TypeSystem\TextSearch;

use SqlSemantics\Model\Definition\TypeSystem\TypeSystemInvariant;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * One option of a text search dictionary, interpreted by the dictionary template, with the argument text the template reads.
 * A null argument names the option alone; ALTER TEXT SEARCH DICTIONARY removes such an option.
 * @visibility public
 * @example Reading the options of a dictionary
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER TEXT SEARCH DICTIONARY my_simple (StopWords = english, Accept)');
 *     $statement->options[0]->name // => 'stopwords'
 *     $statement->options[0]->value // => 'english'
 *     $statement->options[1]->value // => null
 * @example Rejecting an unnamed option
 *     new \SqlSemantics\Model\Definition\TypeSystem\TextSearch\DictionaryOption('', 'english'); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class DictionaryOption
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $name, public readonly ?string $value)
    {
        TypeSystemInvariant::identifier($name);
    }
}
