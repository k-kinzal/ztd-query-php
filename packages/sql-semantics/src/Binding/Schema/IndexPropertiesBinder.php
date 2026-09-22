<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Schema;

use SqlSemantics\Ast\Declaration\IndexDefinition as ParsedIndex;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Schema\Index;

/**
 * Classifies index access and storage options.
 *
 * @visibility SqlSemantics
 */
final class IndexPropertiesBinder
{
    /**
     * Binds storage-parameter values in the statement's dialect.
     */
    public static function bind(ParsedIndex $index, Scope $scope): Index\Properties
    {
        $options = $index->options;
        $parameters = StorageParameters::read($index->source, $scope, ['index_params']);
        OptionBinding::classified($options, ['kind', 'visible', 'invisible', 'key_block_size', 'comment', 'with_parser', 'tablespace', 'engine_attribute', 'secondary_engine_attribute', 'nulls_distinct', 'if_not_exists', 'concurrently', ...array_map(static fn ($parameter): string => implode('.', $parameter->name->parts), $parameters)]);
        return new Index\Properties(
            Index\Kind::from(OptionBinding::string($options, 'kind') ?? 'ordinary'),
            isset($options['invisible']) ? false : (isset($options['visible']) ? true : null),
            OptionBinding::integer($options, 'key_block_size'),
            OptionBinding::string($options, 'comment'),
            OptionBinding::qualified($options, 'with_parser'),
            OptionBinding::string($options, 'tablespace'),
            OptionBinding::string($options, 'engine_attribute'),
            OptionBinding::string($options, 'secondary_engine_attribute'),
            ($options['nulls_distinct'] ?? true) !== false,
            $parameters,
        );
    }
}
