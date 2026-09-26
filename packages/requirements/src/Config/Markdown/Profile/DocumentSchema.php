<?php

declare(strict_types=1);

namespace Requirements\Config\Markdown\Profile;

use JsonException;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Node\Block\Document;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use Requirements\Config\Markdown\Nodes;
use Requirements\Config\SchemaValidator;
use Requirements\Input\Fields;
use Requirements\Input\InvalidInputException;
use stdClass;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Evaluates the bundled document-schema profile; it is not a general dialect implementation.
 *
 * The profile constrains the frontmatter, requires one repeating item section per top-level
 * heading and lists the blocks a section may contain.
 */
final class DocumentSchema
{
    /**
     * Checks a parsed Markdown definition against the bundled profile.
     *
     * @param Document $document The parsed body
     * @param stdClass $frontmatter The frontmatter
     * @param string $file The definition file
     *
     * @throws InvalidInputException When the frontmatter, the headings or the blocks break the profile
     * @throws JsonException When the profile's frontmatter schema cannot be converted
     * @throws ParseException When the bundled profile is malformed
     */
    public function validate(Document $document, stdClass $frontmatter, string $file): void
    {
        $schema = Fields::mapping(Yaml::parseFile(SchemaValidator::path('definition.document.yaml')), 'document-schema');
        Fields::keys($schema, ['$schema', 'frontmatter', 'maxDepth', 'additionalSections', 'additionalBlocks', 'sections', 'allBlocks'], 'document-schema');
        $header = clone $frontmatter;
        unset($header->{'$schema'});
        $frontSchema = json_decode(json_encode($schema['frontmatter'], JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
        if (!$frontSchema instanceof stdClass) {
            throw new InvalidInputException('The document profile needs an object frontmatter schema.');
        }
        $error = (new Validator())->validate($header, $frontSchema)->error();
        if ($error !== null) {
            throw new InvalidInputException("$file: document-schema frontmatter: " . json_encode((new ErrorFormatter())->format($error), JSON_THROW_ON_ERROR));
        }
        $sections = Fields::sequence($schema['sections'], 'sections');
        if (count($sections) !== 1) {
            throw new InvalidInputException('The bundled Markdown profile must have one repeating item section.');
        }
        $section = Fields::mapping($sections[0], 'section');
        Fields::keys($section, ['header', 'minContains', 'maxContains', 'blocks', 'additionalBlocks', 'additionalSections'], 'section');
        $blocks = [];
        $count = 0;
        foreach ($document->children() as $node) {
            if ($node instanceof Heading) {
                if ($count > 0) {
                    SectionBlocks::check($blocks, $section, $file);
                } elseif ($blocks !== [] && ($schema['additionalBlocks'] ?? true) === false) {
                    throw new InvalidInputException("$file: document-schema forbids content before the first heading.");
                }
                if ($node->getLevel() !== ($schema['maxDepth'] ?? 1) || !TextConstraint::matches(Nodes::text($node), Fields::mapping($section['header'], 'header'))) {
                    throw new InvalidInputException("$file: document-schema requires top-level item ID headings.");
                }
                ++$count;
                $blocks = [];
            } else {
                $blocks[] = $node;
                AllowedBlocks::check($node, Fields::mapping($schema['allBlocks'], 'allBlocks'), $file);
            }
        }
        Occurrences::check($count, $section, "$file: item sections");
        if ($count > 0) {
            SectionBlocks::check($blocks, $section, $file);
        }
    }
}
