<?php

namespace App\Services\Scrapers;

interface ScraperInterface
{
    public function scrape(string $url): array;
}
