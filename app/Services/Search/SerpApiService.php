<?php

namespace App\Services\Search;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SerpApiService
{
    private string $apiKey;
    private string $baseUrl = 'https://serpapi.com/search.json';

    public function __construct()
    {
        $this->apiKey = env('SERPAPI_KEY', '');
    }

    /**
     * Execute a Google search via SerpAPI.
     */
    public function search(string $query, array $params = []): array
    {
        if (empty($this->apiKey)) {
            Log::warning('SerpAPI key not configured');
            return [];
        }

        $defaultParams = [
            'engine' => 'google',
            'q' => $query,
            'api_key' => $this->apiKey,
            'hl' => $params['hl'] ?? 'es',
            'gl' => $params['gl'] ?? 'ar',
            'num' => $params['num'] ?? 10,
        ];

        if (isset($params['location'])) {
            $defaultParams['location'] = $params['location'];
        }

        try {
            $response = Http::timeout(30)
                ->get($this->baseUrl, $defaultParams);

            if (!$response->successful()) {
                Log::error('SerpAPI error', ['status' => $response->status()]);
                return [];
            }

            $data = $response->json();

            return $this->parseResults($data);

        } catch (\Throwable $e) {
            Log::error('SerpAPI exception', ['message' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Search Google Maps for local businesses.
     */
    public function searchMaps(string $query, string $location = 'Cordoba, Argentina'): array
    {
        if (empty($this->apiKey)) {
            return [];
        }

        try {
            $coords = $this->getCoordinates($location);
            $mapsQuery = trim($query . ' ' . $location);
            $response = Http::timeout(30)->get($this->baseUrl, [
                'engine' => 'google_maps',
                'q' => $mapsQuery,
                'api_key' => $this->apiKey,
                'll' => $coords,
                'hl' => 'es',
            ]);

            if (!$response->successful()) {
                return [];
            }

            $data = $response->json();

            return $this->parseMapsResults($data);

        } catch (\Throwable $e) {
            Log::error('SerpAPI Maps exception', ['message' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Parse organic search results.
     */
    private function parseResults(array $data): array
    {
        $results = [];

        foreach ($data['organic_results'] ?? [] as $item) {
            $results[] = [
                'title' => $item['title'] ?? '',
                'url' => $item['link'] ?? '',
                'snippet' => $item['snippet'] ?? '',
                'position' => $item['position'] ?? 0,
                'source' => 'google_organic',
            ];
        }

        return $results;
    }

    /**
     * Parse Google Maps results.
     */
    private function parseMapsResults(array $data): array
    {
        $results = [];

        foreach ($data['local_results'] ?? [] as $item) {
            $results[] = [
                'title' => $item['title'] ?? '',
                'url' => $item['website'] ?? '',
                'phone' => $item['phone'] ?? '',
                'address' => $item['address'] ?? '',
                'instagram' => $this->extractInstagramHandle($item['website'] ?? ''),
                'rating' => $item['rating'] ?? null,
                'reviews' => $item['reviews'] ?? 0,
                'type' => $item['type'] ?? '',
                'source' => 'google_maps',
            ];
        }

        return $results;
    }

    /**
     * Get approximate coordinates for a location name.
     */
    private function getCoordinates(string $location): string
    {
        $coords = [
            'Cordoba, Argentina' => '@-31.4201,-64.1888,14z',
            'Buenos Aires, Argentina' => '@-34.6037,-58.3816,14z',
            'Rosario, Argentina' => '@-32.9468,-60.6393,14z',
            'Mendoza, Argentina' => '@-32.8895,-68.8458,14z',
        ];

        return $coords[$location] ?? '@-31.4201,-64.1888,14z';
    }

    private function extractInstagramHandle(string $website): ?string
    {
        if (preg_match('/instagram\.com\/([A-Za-z0-9._]+)/i', $website, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
