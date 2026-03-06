<?php

namespace Assistant\Module\Collection\Extension\Analysis;

interface CheckerInterface
{
    public function getCategory(): AnalysisCategory;

    /** @return AnalysisIssue[] */
    public function check(): array;
}
