<?php

declare(strict_types=1);

namespace Requirements\Markdown;

use InvalidArgumentException;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;
use stdClass;

final class Badges
{
    /** @var array<string, array<string, array{url: string, title: ?string}>> */
    public array $images = [];

    public static function isParagraph(Node $node): bool
    {
        return $node instanceof Paragraph && $node->firstChild() instanceof Image;
    }

    public function read(Node $node, stdClass $item): void
    {
        foreach ($node->children() as $image) {
            if ($image instanceof Newline || ($image instanceof Text && trim($image->getLiteral()) === '')) {
                continue;
            }
            if (!$image instanceof Image || $image->getUrl() === '') {
                throw new InvalidArgumentException('The badge row must contain only images with nonempty destinations.');
            }
            $value = Nodes::text($image);
            if (trim($value) === '') {
                throw new InvalidArgumentException('Badge alt text must contain its attribute value.');
            }
            $field = self::field($image->getUrl(), $image->getTitle());
            self::validateStaticImage($image->getUrl(), $field, $value);
            if ($field === 'label') {
                $labels = $item->labels ?? [];
                if (!is_array($labels) || in_array($value, $labels, true)) {
                    throw new InvalidArgumentException('Label badges must be unique.');
                }
                $item->labels = [...$labels, $value];
            } else {
                if (property_exists($item, $field)) {
                    throw new InvalidArgumentException("Duplicate '$field' badge or field.");
                }
                $item->{$field} = $value;
            }
            $this->images[$field][$value] = ['url' => $image->getUrl(), 'title' => $image->getTitle()];
        }
    }

    public static function field(string $url, ?string $title): string
    {
        if ($title !== null) {
            if (!in_array($title, ['kind', 'status', 'origin', 'category', 'label'], true)) {
                throw new InvalidArgumentException('A custom badge title must be kind, status, origin, category or label.');
            }
            return $title;
        }
        if (parse_url($url, PHP_URL_HOST) === 'img.shields.io' && preg_match('~^/badge/(kind|status|origin|category|label)-~', (string) parse_url($url, PHP_URL_PATH), $match) === 1) {
            return $match[1];
        }
        return 'label';
    }

    private static function validateStaticImage(string $url, string $field, string $value): void
    {
        $path = rawurldecode((string) parse_url($url, PHP_URL_PATH));
        if (parse_url($url, PHP_URL_HOST) !== 'img.shields.io' || preg_match('~^/badge/(kind|status|origin|category|label)-(.*)$~', $path, $match) !== 1) {
            return;
        }
        $parts = explode('-', str_replace('--', "\0", $match[2]));
        $message = str_replace(["\0", '_'], ['-', ' '], str_replace('__', "\1", $parts[0]));
        $message = str_replace("\1", '_', $message);
        if (count($parts) !== 2 || $match[1] !== $field || $message !== $value) {
            throw new InvalidArgumentException('Static badge image text and role must agree with its alt text and title.');
        }
    }

    /** @param array{url: string, title: ?string}|null $image */
    public static function render(string $field, string $value, ?array $image): string
    {
        $color = $field === 'status' && $value === 'unsupported' ? 'orange' : 'blue';
        $url = $image['url'] ?? ('https://img.shields.io/badge/' . $field . '-' . rawurlencode(str_replace(['-', '_'], ['--', '__'], $value)) . '-' . $color);
        $title = isset($image['title']) ? ' "' . str_replace('"', '&quot;', $image['title']) . '"' : '';
        return '![' . Nodes::escape($value) . '](' . Nodes::destination($url) . $title . ')';
    }
}
