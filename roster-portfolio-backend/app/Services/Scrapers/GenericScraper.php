<?php

namespace App\Services\Scrapers;

use Spatie\Browsershot\Browsershot;
use Symfony\Component\DomCrawler\Crawler;

class GenericScraper implements ScraperInterface    
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
            'basicInfo' => $this->extractBasicInfo($crawler, $url, $html),
            'employers' => $this->extractEmployers($crawler),
        ];
    }

    protected function extractBasicInfo(Crawler $crawler, string $url, string $html): array
    {
        $basicInfo = [
            'name' => 'Unknown',
            'title' => '',
            'bio' => '',
            'email' => '',
            'location' => '',
            'website' => $url,
        ];

        // Extract name
        $nameSelectors = ['h1', '.name', '#name', '[data-name]', 'title'];
        foreach ($nameSelectors as $selector) {
            try {
                $name = $this->extractText($crawler, [$selector]);
                if (!empty($name) && strlen($name) < 100) {
                    $basicInfo['name'] = $name;
                    break;
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        // Fallback: parse <title> tag
        if ($basicInfo['name'] === 'Unknown') {
            try {
                $titleText = $crawler->filter('title')->text();
                if (!empty(trim($titleText))) {
                    $parts = preg_split('/[\|\-–]/', $titleText);
                    $first = trim($parts[0]);
                    if (strlen($first) < 100) {
                        $basicInfo['name'] = $first;
                    }
                }
            } catch (\Exception $e) {}
        }

        // Extract title/role
        $titleSelectors = ['.title', '.role', '.job-title', 'h2', '.subtitle'];
        $basicInfo['title'] = $this->extractText($crawler, $titleSelectors);

        // Extract bio/description
        $bioSelectors = ['.bio', '.description', '.about', 'p'];
        $bioText = $this->extractText($crawler, $bioSelectors);
        if (strlen($bioText) > 50 && strlen($bioText) < 1000) {
            $basicInfo['bio'] = $bioText;
        }

        // Extract email
        try {
            $emailPattern = '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/';
            if (preg_match($emailPattern, $html, $matches)) {
                $basicInfo['email'] = $matches[0];
            }
        } catch (\Exception $e) {}

        return $basicInfo;
    }

    protected function extractEmployers(Crawler $crawler): array
    {
        $employers = [];

        $sectionSelectors = ['.work-item', '.portfolio-item', '.project', '.experience-item','.gallery-item','.media-item','.card','.portfolio-item','.project'
    ];

        foreach ($sectionSelectors as $selector) {
            try {
                $crawler->filter($selector)->each(function (Crawler $node, $i) use (&$employers) {
                    $employer = [
                        'name' => $this->extractText($node, ['h3', '.company', '.client', '.project-title']),
                        'role' => $this->extractText($node, ['.role', '.position', '.title']),
                        'period' => $this->extractText($node, ['.period', '.date', '.year']),
                        'description' => $this->extractText($node, ['.description', 'p']),
                        'videos' => $this->extractVideos($node),
                    ];

                    if (!empty($employer['name'])) {
                        $employers[] = $employer;
                    }
                });

                if (!empty($employers)) {
                    break;
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        // Fallback: add all videos under a generic employer
        if (empty($employers)) {
            $videos = $this->extractVideos($crawler);
            if (!empty($videos)) {
                $employers[] = [
                    'name' => 'Portfolio Work',
                    'role' => 'Various Projects',
                    'period' => '',
                    'description' => 'Collection of portfolio work and projects',
                    'videos' => $videos,
                ];
            }
        }

        return $employers;
    }

    protected function extractVideos(Crawler $crawler): array
    {
        $videos = [];
        $existingUrls = [];

        $videoSelectors = ['iframe[src*="youtube"]','iframe[src*="vimeo"]','video','.video-wrapper iframe','.media-item iframe','[data-video]'];

        foreach ($videoSelectors as $selector) {
            try {
                $crawler->filter($selector)->each(function (Crawler $node, $i) use (&$videos, &$existingUrls) {
                    $video = [
                        'title' => $this->getVideoTitle($node),
                        'url' => $this->normalizeVideoUrl($this->getVideoUrl($node)),
                        'thumbnail' => $this->getVideoThumbnail($node),
                        'duration' => '',
                    ];

                    if (!empty($video['url']) && !in_array($video['url'], $existingUrls)) {
                        $videos[] = $video;
                        $existingUrls[] = $video['url'];
                    }
                });
            } catch (\Exception $e) {
                continue;
            }
        }

        return $videos;
    }

    protected function extractText(Crawler $node, array $selectors): string
    {
        foreach ($selectors as $selector) {
            try {
                $text = $node->filter($selector)->first()->text();
                $text = preg_replace('/\s+/', ' ', trim($text));
                if (!empty($text)) {
                    return $text;
                }
            } catch (\Exception $e) {
                continue;
            }
        }
        return '';
    }

    protected function getVideoTitle(Crawler $node): string
    {
        $titleSources = ['title', 'alt', 'data-title'];
        foreach ($titleSources as $attr) {
            $title = $node->attr($attr);
            if (!empty($title)) {
                return trim($title);
            }
        }
        return 'Video';
    }

    protected function getVideoUrl(Crawler $node): string
    {
        $urlSources = ['src', 'data-src', 'href'];
        foreach ($urlSources as $attr) {
            $url = $node->attr($attr);
            if (!empty($url)) {
                return trim($url);
            }
        }
        return '';
    }

    protected function getVideoThumbnail(Crawler $node): string
    {
        $thumbnailSources = ['poster', 'data-thumbnail', 'data-poster'];
        foreach ($thumbnailSources as $attr) {
            $thumbnail = $node->attr($attr);
            if (!empty($thumbnail)) {
                return trim($thumbnail);
            }
        }
        return '';
    }

    protected function normalizeVideoUrl(string $url): string
    {
        if (strpos($url, 'youtube.com/embed/') !== false) {
            return str_replace('youtube.com/embed/', 'youtube.com/watch?v=', $url);
        }

        if (strpos($url, 'player.vimeo.com/video/') !== false) {
            preg_match('/video\/(\d+)/', $url, $matches);
            return isset($matches[1]) ? 'https://vimeo.com/' . $matches[1] : $url;
        }

        return $url;
    }
}
