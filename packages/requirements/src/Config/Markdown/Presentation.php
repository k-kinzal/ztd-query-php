<?php

declare(strict_types=1);

namespace Requirements\Config\Markdown;

/**
 * What a Markdown definition says beyond its data: link targets, badge images and citations.
 *
 * Reading records these per item so that writing the definition back reproduces them, and the
 * links to other items are kept to be checked once every definition is loaded.
 */
final class Presentation
{
    /**
     * @var array<string, array<string, array<string, string>>> Link targets by item ID, field and linked ID
     */
    public array $links = [];

    /**
     * @var array<string, array<string, array<string, array{url: string, title: ?string}>>> Badge images by item ID, field and value
     */
    public array $badges = [];

    /**
     * @var array<string, list<array{url: string, label: string}|null>> Citation links by item ID, one per evidence quotation
     */
    public array $citations = [];

    /**
     * @var list<Reference> Links to other items
     */
    public array $references = [];
}
