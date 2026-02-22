<?php
declare(strict_types=1);

namespace Vendor\ImageSearch\Model;

use Vendor\ImageSearch\Api\SearchServiceInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Psr\Log\LoggerInterface;

class SearchService implements SearchServiceInterface
{
    private const INDEX_ENDPOINT = '/index/product/%s';
    private const SEARCH_ENDPOINT = '/search-by-image';

    /**
     * @param Curl $curl
     * @param Json $json
     * @param ScopeConfigInterface $scopeConfig
     * @param LoggerInterface $logger
     * @param CacheManager $cacheManager
     */
    public function __construct(
        private Curl $curl,
        private Json $json,
        private ScopeConfigInterface $scopeConfig,
        private LoggerInterface $logger,
        private CacheManager $cacheManager
    ) {
        $this->curl->addHeader('Content-Type', 'application/json');
    }

    /**
     * @inheritDoc
     */
    public function indexProductImages(string $sku, array $imageData): array
    {
        $url = $this->getAiServiceUrl() . sprintf(self::INDEX_ENDPOINT, $sku);
        
        try {
            $this->curl->post($url, $this->json->serialize($imageData));
            $response = $this->json->unserialize($this->curl->getBody());
            
            if ($this->curl->getStatus() !== 200) {
                throw new \Exception('AI service error: ' . ($response['message'] ?? 'Unknown error'));
            }
            
            return $response;
        } catch (\Exception $e) {
            $this->logger->error('Image indexing failed: ' . $e->getMessage());
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * @inheritDoc
     */
    public function searchByImage(string $imageContent, array $filters = []): array
    {
        $url = $this->getAiServiceUrl() . self::SEARCH_ENDPOINT;
        
        try {
            $payload = [
                'image' => $imageContent,
                'filters' => $this->prepareFilters($filters)
            ];
            
            $this->curl->post($url, $this->json->serialize($payload));
            
            if ($this->curl->getStatus() !== 200) {
                throw new \Exception('Search request failed with status: ' . $this->curl->getStatus());
            }
            
            return $this->json->unserialize($this->curl->getBody());
        } catch (\Exception $e) {
            $this->logger->error('Image search failed: ' . $e->getMessage());
            return ['hits' => ['hits' => []]];
        }
    }

    /**
     * Get AI service URL from config
     *
     * @return string
     */
    private function getAiServiceUrl(): string
    {
        return $this->scopeConfig->getValue('imagesearch/general/ai_service_url');
    }

    /**
     * Prepare Magento filters for AI service
     *
     * @param array $filters
     * @return array
     */
    private function prepareFilters(array $filters): array
    {
        $prepared = [];
        
        if (isset($filters['store_id'])) {
            $prepared[] = ['term' => ['store_id' => $filters['store_id']]];
        }
        
        if (isset($filters['category_ids'])) {
            $prepared[] = ['terms' => ['category_ids' => $filters['category_ids']]];
        }
        
        // Always filter by visible products in stock
        $prepared[] = ['term' => ['visibility' => ['in' => [2, 3, 4]]]];
        $prepared[] = ['term' => ['status' => 1]];
        $prepared[] = ['term' => ['in_stock' => true]];
        
        return $prepared;
    }
}