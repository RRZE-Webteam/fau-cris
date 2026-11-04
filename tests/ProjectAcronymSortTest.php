<?php

namespace RRZE\Cris\Tests;

use RRZE\Cris\Tools;

/**
 * Sorting project lists by their CRIS acronym (cfacro).
 *
 * @covers \RRZE\Cris\Tools::sort_projects_by_acronym
 */
class ProjectAcronymSortTest extends CrisTestCase
{
    /**
     * Build a project list in the shape the webservice returns: objects
     * carrying an "attributes" array. A null acronym means the cfacro key is
     * missing altogether, which is how CRIS delivers a project that has none.
     *
     * @param array<string,string|null> $acronyms Project key => acronym.
     * @return array<string,object>
     */
    private function projects(array $acronyms): array
    {
        $projects = [];
        foreach ($acronyms as $key => $acronym) {
            $project = new \stdClass();
            $project->attributes = ['cftitle' => 'Title of ' . $key];
            if ($acronym !== null) {
                $project->attributes['cfacro'] = $acronym;
            }
            $projects[$key] = $project;
        }
        return $projects;
    }

    /** @param array<string,object> $projects */
    private function acronymsOf(array $projects): array
    {
        return array_values(array_map(
            fn($project) => $project->attributes['cfacro'] ?? '',
            $projects
        ));
    }

    public function test_sorts_acronyms_from_a_to_z(): void
    {
        $sorted = Tools::sort_projects_by_acronym(
            $this->projects(['p1' => 'ZEBRA', 'p2' => 'ALPHA', 'p3' => 'MIKRO']),
            'acro_asc'
        );

        $this->assertSame(['ALPHA', 'MIKRO', 'ZEBRA'], $this->acronymsOf($sorted));
    }

    public function test_sorts_acronyms_from_z_to_a(): void
    {
        $sorted = Tools::sort_projects_by_acronym(
            $this->projects(['p1' => 'ZEBRA', 'p2' => 'ALPHA', 'p3' => 'MIKRO']),
            'acro_desc'
        );

        $this->assertSame(['ZEBRA', 'MIKRO', 'ALPHA'], $this->acronymsOf($sorted));
    }

    public function test_sorting_ignores_the_case_of_the_acronym(): void
    {
        $sorted = Tools::sort_projects_by_acronym(
            $this->projects(['p1' => 'beta', 'p2' => 'Alpha', 'p3' => 'GAMMA']),
            'acro_asc'
        );

        $this->assertSame(['Alpha', 'beta', 'GAMMA'], $this->acronymsOf($sorted));
    }

    /** SFB2 must come before SFB10, not after it as a plain string sort would have it. */
    public function test_compares_digits_inside_acronyms_as_numbers(): void
    {
        $sorted = Tools::sort_projects_by_acronym(
            $this->projects(['p1' => 'SFB10', 'p2' => 'SFB2', 'p3' => 'SFB1']),
            'acro_asc'
        );

        $this->assertSame(['SFB1', 'SFB2', 'SFB10'], $this->acronymsOf($sorted));
    }

    public function test_projects_without_an_acronym_go_last_when_ascending(): void
    {
        $sorted = Tools::sort_projects_by_acronym(
            $this->projects(['p1' => null, 'p2' => 'ZEBRA', 'p3' => '', 'p4' => 'ALPHA']),
            'acro_asc'
        );

        $this->assertSame(['ALPHA', 'ZEBRA', '', ''], $this->acronymsOf($sorted));
    }

    /** The empty-last rule holds in both directions: only the named projects reverse. */
    public function test_projects_without_an_acronym_go_last_when_descending(): void
    {
        $sorted = Tools::sort_projects_by_acronym(
            $this->projects(['p1' => null, 'p2' => 'ALPHA', 'p3' => '', 'p4' => 'ZEBRA']),
            'acro_desc'
        );

        $this->assertSame(['ZEBRA', 'ALPHA', '', ''], $this->acronymsOf($sorted));
    }

    /** An acronym that is nothing but whitespace counts as no acronym at all. */
    public function test_a_whitespace_only_acronym_counts_as_empty(): void
    {
        $sorted = Tools::sort_projects_by_acronym(
            $this->projects(['p1' => '   ', 'p2' => 'ALPHA']),
            'acro_asc'
        );

        $this->assertSame(['p2', 'p1'], array_keys($sorted));
    }

    /** With no acronyms anywhere the incoming (start date) order must survive. */
    public function test_a_list_without_any_acronym_keeps_its_incoming_order(): void
    {
        $sorted = Tools::sort_projects_by_acronym(
            $this->projects(['p1' => null, 'p2' => '', 'p3' => null]),
            'acro_asc'
        );

        $this->assertSame(['p1', 'p2', 'p3'], array_keys($sorted));
    }

    /** make_list() and make_accordion() index by project ID, so keys must survive. */
    public function test_preserves_the_project_keys(): void
    {
        $sorted = Tools::sort_projects_by_acronym(
            $this->projects(['123' => 'ZEBRA', '456' => 'ALPHA']),
            'acro_asc'
        );

        $this->assertSame(['456', '123'], array_map('strval', array_keys($sorted)));
    }
}
