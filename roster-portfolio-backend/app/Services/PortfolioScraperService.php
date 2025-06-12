<?php

namespace App\Services;

use App\Models\Portfolio;
use App\Models\Employer;
use App\Models\Video;
use App\Services\Scrapers\ScraperInterface;
use App\Services\Scrapers\GenericScraper;
use App\Services\Scrapers\BehanceScraper;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Exception;

class PortfolioScraperService
{
    protected Client $httpClient;
    protected array $scrapers = [];

    public function __construct()
    {
       $this->httpClient = new Client([
            'timeout' => 60,              // Total request time
            'connect_timeout' => 10,      // Time to connect to server
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90 Safari/537.36',
                'Accept'     => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            ],
            'allow_redirects' => true,
            'http_errors' => false,
        ]);

        $this->registerScrapers();
    }

    protected function registerScrapers(): void
    {
        $this->scrapers = [
            'behance.net' => new BehanceScraper(),
            'generic'     => new GenericScraper(),
        ];
    }

    public function scrapePortfolio(string $url): array
    {
        try {
            $response = $this->httpClient->get($url);
            $statusCode = $response->getStatusCode();

            if ($statusCode >= 400) {
                throw new Exception("Failed to fetch content. Status code: $statusCode");
            }

            $html = $response->getBody()->getContents();

            $scraper = $this->getScraper($url);
            return $scraper->scrape($url);

        } catch (RequestException $e) {
            Log::error('HTTP Request failed', ['url' => $url, 'message' => $e->getMessage()]);
            throw new Exception("Could not scrape URL: {$url}");
        } catch (Exception $e) {
            Log::error('Scraping failed', ['url' => $url, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    protected function getScraper(string $url): ScraperInterface
    {
        $domain = parse_url($url, PHP_URL_HOST);

        foreach ($this->scrapers as $pattern => $scraper) {
            if ($pattern !== 'generic' && strpos($domain, $pattern) !== false) {
                return $scraper;
            }
        }

        return $this->scrapers['generic'];
    }

    public function storePortfolioData(array $data, string $url): Portfolio
    {
        $portfolio = Portfolio::create([
            'url'      => $url,
            'name'     => $data['basicInfo']['name'] ?? 'Unknown',
            'title'    => $data['basicInfo']['title'] ?? '',
            'bio'      => $data['basicInfo']['bio'] ?? '',
            'email'    => $data['basicInfo']['email'] ?? '',
            'location' => $data['basicInfo']['location'] ?? '',
            'website'  => $data['basicInfo']['website'] ?? $url,
        ]);

        foreach ($data['employers'] ?? [] as $employerData) {
            $employer = Employer::create([
                'portfolio_id' => $portfolio->id,
                'name'         => $employerData['name'] ?? 'Untitled',
                'role'         => $employerData['role'] ?? '',
                'period'       => $employerData['period'] ?? '',
                'description'  => $employerData['description'] ?? '',
            ]);

            foreach ($employerData['videos'] ?? [] as $videoData) {
                if (!empty($videoData['url'])) {
                    Video::create([
                        'employer_id' => $employer->id,
                        'title'       => $videoData['title'] ?? 'Untitled',
                        'url'         => $videoData['url'],
                        'thumbnail'   => $videoData['thumbnail'] ?? '',
                        'duration'    => $videoData['duration'] ?? '',
                    ]);
                }
            }
        }

        return $portfolio;
    }
}
