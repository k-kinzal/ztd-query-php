<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\Replication;

use SqlParser\Parser\Node;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\Definition\TypeSystem\DefinitionArguments;
use SqlSemantics\Binding\Statement\Definition\TypeSystem\DefinitionElement;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Definition\Replication\Publication\PublicationOptions;
use SqlSemantics\Model\Definition\Replication\Publication\PublishedOperation;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Reads the documented publication options, each at most once.
 * @visibility SqlSemantics
 */
final class PublicationOptionReader
{
    /**
     * publish takes a comma-separated operation list and publish_via_partition_root a Boolean; other names are rejected.
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function read(?Node $definition, QueryContext $context): PublicationOptions
    {
        if ($definition === null) {
            return new PublicationOptions();
        }
        $named = DefinitionElement::named(DefinitionElement::list($definition, $context), ['publish', 'publish_via_partition_root'], false);
        $publish = isset($named['publish']) ? self::operations($named['publish'], $context) : null;
        $root = $named['publish_via_partition_root'] ?? null;
        return new PublicationOptions($publish, $root === null ? null : ($root->argument === null || DefinitionArguments::boolean($root->argument, $context)));
    }

    /**
     * Splits the publish list as an identifier list: unquoted items fold to lower case and must name an operation.
     * @return list<PublishedOperation>
     * @throws InvalidSql
     * @throws UnclassifiedSql
     */
    public static function operations(DefinitionElement $element, QueryContext $context): array
    {
        $text = $element->argument === null ? throw new InvalidSql(InputViolation::DefinitionArgument, $element->source) : DefinitionArguments::text($element->argument, $context);
        if (trim($text, " \t\n\r") === '') {
            return [];
        }
        $operations = [];
        foreach (explode(',', $text) as $item) {
            $item = trim($item, " \t\n\r");
            $name = preg_match('/^"(.*)"$/sD', $item, $quoted) === 1 ? str_replace('""', '"', $quoted[1]) : strtolower($item);
            $operations[] = PublishedOperation::tryFrom($name) ?? throw new InvalidSql(InputViolation::DefinitionArgument, $element->source);
        }
        return $operations;
    }
}
