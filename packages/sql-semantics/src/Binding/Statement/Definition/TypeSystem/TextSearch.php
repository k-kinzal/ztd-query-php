<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Statement\Definition\TypeSystem;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\Query\QueryContext;
use SqlSemantics\Binding\Statement\Definition\Catalog\ObjectAddresses;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\TypeSystem\TextSearch\DictionaryOption;
use SqlSemantics\Model\Definition\TypeSystem\TextSearch\MappingChange;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\TextSearch as Statement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InputViolation;

/**
 * Binds the text search parser, template, dictionary, and configuration commands.
 * @visibility SqlSemantics
 */
final class TextSearch
{
    /**
     * Routes CREATE TEXT SEARCH by its object class; a repeated attribute keeps its last argument, as the server does.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function create(Origin $origin, Node $source, QueryContext $context): BoundStatement
    {
        $name = ObjectAddresses::name(Tree::child($source, ['any_name']) ?? throw new UnclassifiedSql('A text search object requires its name.'), $context, 2);
        $elements = DefinitionElement::list(Tree::child($source, ['definition']) ?? $source, $context);
        return match (ObjectAddresses::words($source)[3] ?? '') {
            'PARSER' => self::parser($origin, $name, DefinitionElement::named($elements, ['start', 'gettoken', 'end', 'lextypes', 'headline'], true), $source, $context),
            'TEMPLATE' => self::template($origin, $name, DefinitionElement::named($elements, ['init', 'lexize'], true), $source, $context),
            'DICTIONARY' => self::dictionary($origin, $name, $elements, $source, $context),
            default => self::configuration($origin, $name, DefinitionElement::named($elements, ['parser', 'copy'], true), $source, $context),
        };
    }

    /**
     * A parser requires its start, gettoken, end, and lextypes functions.
     * @param array<string, DefinitionElement> $elements
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function parser(Origin $origin, QualifiedName $name, array $elements, Node $source, QueryContext $context): Statement\CreateTextSearchParserStatement
    {
        $function = static fn (string $key): QualifiedName => self::function($elements[$key] ?? throw new InvalidSql(InputViolation::DefinitionRequirement, $source), $context);
        return new Statement\CreateTextSearchParserStatement($origin, $name, $function('start'), $function('gettoken'), $function('end'), $function('lextypes'), isset($elements['headline']) ? $function('headline') : null);
    }

    /**
     * A template requires its lexize function.
     * @param array<string, DefinitionElement> $elements
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function template(Origin $origin, QualifiedName $name, array $elements, Node $source, QueryContext $context): Statement\CreateTextSearchTemplateStatement
    {
        $lexize = self::function($elements['lexize'] ?? throw new InvalidSql(InputViolation::DefinitionRequirement, $source), $context);
        return new Statement\CreateTextSearchTemplateStatement($origin, $name, $lexize, isset($elements['init']) ? self::function($elements['init'], $context) : null);
    }

    /**
     * A dictionary requires its template; every other option belongs to the template.
     * @param list<DefinitionElement> $elements
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function dictionary(Origin $origin, QualifiedName $name, array $elements, Node $source, QueryContext $context): Statement\CreateTextSearchDictionaryStatement
    {
        $template = null;
        $options = [];
        foreach ($elements as $element) {
            if ($element->name === 'template') {
                $template = DefinitionArguments::objectName($element, $context);
                continue;
            }
            $options[] = self::option($element, $context);
        }
        return new Statement\CreateTextSearchDictionaryStatement($origin, $name, $template ?? throw new InvalidSql(InputViolation::DefinitionRequirement, $source), $options);
    }

    /**
     * A configuration names its parser or the configuration it copies, not both.
     * @param array<string, DefinitionElement> $elements
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function configuration(Origin $origin, QualifiedName $name, array $elements, Node $source, QueryContext $context): Statement\CreateTextSearchConfigurationStatement|Statement\CopyTextSearchConfigurationStatement
    {
        return match (true) {
            isset($elements['parser'], $elements['copy']), !isset($elements['parser']) && !isset($elements['copy']) => throw new InvalidSql(InputViolation::DefinitionRequirement, $source),
            isset($elements['parser']) => new Statement\CreateTextSearchConfigurationStatement($origin, $name, DefinitionArguments::objectName($elements['parser'], $context)),
            default => new Statement\CopyTextSearchConfigurationStatement($origin, $name, DefinitionArguments::objectName($elements['copy'], $context)),
        };
    }

    /**
     * ALTER TEXT SEARCH DICTIONARY sets or removes template options.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function alterDictionary(Origin $origin, Node $source, QueryContext $context): Statement\AlterTextSearchDictionaryStatement
    {
        $options = array_map(static fn (DefinitionElement $element): DictionaryOption => self::option($element, $context), DefinitionElement::list(Tree::child($source, ['definition']) ?? $source, $context));
        return new Statement\AlterTextSearchDictionaryStatement($origin, ObjectAddresses::name(Tree::child($source, ['any_name']) ?? throw new UnclassifiedSql('A dictionary change requires its dictionary.'), $context, 2), Collections::nonEmpty($options));
    }

    /**
     * ALTER TEXT SEARCH CONFIGURATION adds, replaces, or drops token mappings, or replaces one dictionary.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function alterConfiguration(Origin $origin, Node $source, QueryContext $context): BoundStatement
    {
        $names = array_map(static fn (Node $name): QualifiedName => ObjectAddresses::name($name, $context, 2), Tree::outer($source, ['any_name']));
        $configuration = $names[0] ?? throw new UnclassifiedSql('A configuration change requires its configuration.');
        $list = Tree::child($source, ['name_list']);
        $tokens = $list === null ? null : Collections::nonEmpty(array_map(static fn (Node $token): string => $context->tables->identifiers->name($token->tokens()[0]), Tree::outer($list, ['name'])));
        $words = ObjectAddresses::words($source);
        $change = $words[count(ObjectAddresses::words(Tree::child($source, ['any_name']) ?? $source)) + 4] ?? '';
        return match (true) {
            in_array('REPLACE', $words, true) => new Statement\ReplaceTextSearchDictionaryStatement($origin, $configuration, $names[1] ?? throw new UnclassifiedSql('A replacement requires the replaced dictionary.'), $names[2] ?? throw new UnclassifiedSql('A replacement requires the new dictionary.'), $tokens),
            $change === 'DROP' => new Statement\DropTextSearchMappingStatement($origin, $configuration, $tokens ?? throw new UnclassifiedSql('DROP MAPPING requires token types.'), in_array('EXISTS', $words, true)),
            default => new Statement\MapTextSearchTokensStatement($origin, $configuration, MappingChange::from($change), $tokens ?? throw new UnclassifiedSql('A mapping requires token types.'), Collections::nonEmpty(array_slice($names, 1))),
        };
    }

    /**
     * A function named by a parser or template attribute.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function function(DefinitionElement $element, QueryContext $context): QualifiedName
    {
        return DefinitionArguments::name($element->argument ?? throw new InvalidSql(InputViolation::DefinitionArgument, $element->source), $context);
    }

    /**
     * A template option with the text the template reads, or without an argument.
     * @throws UnclassifiedSql
     * @throws InvalidSql
     */
    public static function option(DefinitionElement $element, QueryContext $context): DictionaryOption
    {
        return new DictionaryOption($element->name, $element->argument === null ? null : DefinitionArguments::text($element->argument, $context));
    }
}
