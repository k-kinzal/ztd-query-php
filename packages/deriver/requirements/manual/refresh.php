<?php

declare(strict_types=1);

use Requirements\Source\ResourceLoader;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Yaml\Yaml;

require __DIR__ . '/../../../requirements/vendor/autoload.php';

// Review the extracted manual excerpts, update the printed digests in the definitions,
// then regenerate requirements-snapshot.json with requirements coverage --write-snapshot.
$definitions = dirname(__DIR__);
$loader = new ResourceLoader();

foreach (['constants', 'function', 'syntax-forms', 'syntax-keywords', 'syntax-operators'] as $name) {
    $source = Yaml::parseFile("$definitions/$name.yaml")['source'];
    $uri = $source['uri'];
    $page = new Crawler();
    $page->addHtmlContent($loader->fetch($uri));

    [$selector, $open, $close, $wrapLink] = match ($name) {
        'constants' => ['[id="reserved.constants.core"] > dl > dt', '<div id="reserved.constants.core"><dl>', '</dl></div>', false],
        'function' => [$source['selector'], '<section id="layout-content"><ul class="index-for-refentry">', '</ul></section>', true],
        'syntax-forms' => [$source['selector'], '<section id="layout-content"><ul class="chunklist">', '</ul></section>', true],
        'syntax-keywords' => ['[id="reserved.keywords"] > table', '<div id="reserved.keywords">', '</div>', false],
        'syntax-operators' => ['[id="language.operators.precedence"] > table.doctable', '<div id="language.operators.precedence">', '</div>', false],
    };

    $nodes = $page->filter($selector)->each(static function (Crawler $crawler) use ($wrapLink): string {
        $html = $crawler->outerHtml();
        return $wrapLink ? "<li>$html</li>" : $html;
    });
    if ($nodes === []) {
        throw new RuntimeException("No manual content matched $uri.");
    }

    $html = "<!doctype html>\n<meta charset=\"utf-8\">\n"
        . "<!-- Extracted from $uri. Copyright PHP Documentation Group.\n"
        . "     Creative Commons Attribution 3.0 or later (https://creativecommons.org/licenses/by/3.0/). -->\n"
        . $open . "\n" . implode("\n", $nodes) . "\n" . $close . "\n";
    $html = implode("\n", array_map('rtrim', explode("\n", $html)));
    $path = $source['snapshot'];
    if (file_put_contents(dirname($definitions) . "/$path", $html) === false) {
        throw new RuntimeException("Could not write $path.");
    }
    printf("%s  %s\n", hash('sha256', $html), $path);
}
