<?php

declare(strict_types=1);

namespace Requirements\Report;

use Requirements\Model\Project;
use Requirements\Source\Registry;
use Throwable;

/**
 * Selects every declared scope, resolves every evidence entry and records which units the
 * specifications claim.
 *
 * Source extensions report retrieval failures and unsupported syntax by throwing, so each scope
 * and each evidence entry is read on its own and any failure becomes an error of the analysis.
 * An item with invalid evidence claims nothing, and neither do specifications refining it.
 */
final class Analyzer
{
    /**
     * Analyzes the sources and evidence of a project.
     *
     * @param Project $project The loaded project
     * @param bool $live Whether to read the current source URIs instead of pinned snapshots
     *
     * @return Analysis The units, scopes, errors and resolved evidence
     */
    public function analyze(Project $project, bool $live = false): Analysis
    {
        $registry = new Registry($project->sourceExtensions);
        $collector = new UnitCollector();
        $errors = [];
        foreach ($project->sources as $source) {
            $collector->open($source);
            try {
                $collector->collect($source, $registry->get($source->format)->select($source, $source->selector, $project->directory, $live));
            } catch (Throwable $error) {
                $errors[] = "$source->id: " . $error->getMessage();
            }
        }
        $matcher = new EvidenceMatcher($registry, $project->directory, $live);
        $evidence = [];
        $invalid = [];
        foreach ($project->items as $item) {
            $evidence[$item->id] = [];
            $source = $item->source;
            if ($source === null) {
                continue;
            }
            foreach ($item->evidence as $excerpt) {
                try {
                    $evidence[$item->id][] = $matcher->match($source, $excerpt, $collector->scopes);
                } catch (Throwable $error) {
                    $invalid[$item->id] = true;
                    $errors[] = "$item->id: " . $error->getMessage();
                }
            }
        }
        $units = (new Claims())->assign($collector->units, $project->items, $evidence, $invalid);
        return new Analysis($units, $collector->scopes, $errors, $evidence);
    }
}
