<?php

namespace App\Services\Scrapers;

use Spatie\Browsershot\Browsershot;
use Symfony\Component\DomCrawler\Crawler;

class BehanceScraper implements ScraperInterface
{
    public function scrape(string $url): array
    {
       $html = Browsershot::url($url)
    ->setChromePath('/usr/bin/chromium')
    ->timeout(120) // Allow more time
    ->noSandbox()
    ->waitUntilNetworkIdle()
    ->bodyHtml();

        $crawler = new Crawler($html);

        return [
            'basicInfo' => $this->extractBehanceBasicInfo($crawler, $url, $html),
            'employers' => $this->extractBehanceProjects($crawler),
        ];
    }

    protected function extractBehanceBasicInfo(Crawler $crawler, string $url, string $html): array
    {
        $basicInfo = [
            'name' => 'Unknown',
            'title' => '',
            'bio' => '',
            'email' => '',
            'location' => '',
            'website' => $url,
        ];

        try {
            $basicInfo['name'] = trim($crawler->filter('.profile-name, .owner-name')->first()->text());
        } catch (\Exception $e) {}

        try {
            $basicInfo['title'] = trim($crawler->filter('.profile-title, .occupation')->first()->text());
        } catch (\Exception $e) {}

        try {
            $basicInfo['bio'] = trim($crawler->filter('.bio, .about, .description, .profile-bio')->first()->text());
        } catch (\Exception $e) {}

        try {
            $basicInfo['location'] = trim($crawler->filter('.location, .profile-location')->first()->text());
        } catch (\Exception $e) {}

        // Extract email using regex
        $emailPattern = '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/';
        if (preg_match($emailPattern, $html, $matches)) {
            $basicInfo['email'] = $matches[0];
        }

        return $basicInfo;
    }

    protected function extractBehanceProjects(Crawler $crawler): array
    {
        $projects = [];

        try {
            $crawler->filter('.project-cover, .work-item')->each(function (Crawler $node, $i) use (&$projects) {
                $project = [
                    'name' => $this->extractText($node, ['.project-title', '.title']),
                    'role' => 'Creative Project',
                    'period' => '',
                    'description' => $this->extractText($node, ['.project-description', '.description']),
                    'videos' => $this->extractVideos($node),
                ];

                if (!empty($project['name'])) {
                    $projects[] = $project;
                }
            });
        } catch (\Exception $e) {}

        return $projects;
    }

    protected function extractText(Crawler $node, array $selectors): string
    {
        foreach ($selectors as $selector) {
            try {
                $text = $node->filter($selector)->first()->text();
                if (!empty(trim($text))) {
                    return trim($text);
                }
            } catch (\Exception $e) {
                continue;
            }
        }
        return '';
    }

    protected function extractVideos(Crawler $node): array
    {
        $videos = [];
        $selectors = ['iframe[src*="youtube"]', 'iframe[src*="vimeo"]', 'video'];

        foreach ($selectors as $selector) {
            try {
                $node->filter($selector)->each(function (Crawler $videoNode) use (&$videos) {
                    $video = [
                        'title' => $videoNode->attr('title') ?? 'Video',
                        'url' => $videoNode->attr('src') ?? '',
                        'thumbnail' => $videoNode->attr('poster') ?? '',
                        'duration' => '',
                    ];

                    if (!empty($video['url'])) {
                        $videos[] = $video;
                    }
                });
            } catch (\Exception $e) {
                continue;
            }
        }

        return $videos;
    }
}
