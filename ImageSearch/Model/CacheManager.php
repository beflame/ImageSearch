<?php
declare(strict_types=1);

namespace Vendor\ImageSearch\Model;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\App\Config\ScopeConfigInterface;

class CacheManager
{
    private const CACHE_KEY_PREFIX = 'img_search_';
    private const DEFAULT_TTL = 300; // 5 minutes

    /**
     * @param CacheInterface $cache
     * @param Json $json
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private CacheInterface $cache,
        private Json $json,
        private ScopeConfigInterface $scopeConfig
    ) {}

    /**
     * Save search results to cache
     *
     * @param string $hash
     * @param array $data
     * @return void
     */
    public function saveResults(string $hash, array $data): void
    {
        $ttl = $this->getCacheTtl();
        $this->cache->save(
            $this->json->serialize($data),
            $this->getCacheKey($hash),
            ['imagesearch_results'],
            $ttl
        );
    }

    /**
     * Load search results from cache
     *
     * @param string $hash
     * @return array|null
     */
    public function loadResults(string $hash): ?array
    {
        $cached = $this->cache->load($this->getCacheKey($hash));
        
        if ($cached) {
            return $this->json->unserialize($cached);
        }
        
        return null;
    }

    /**
     * Get cache key for hash
     *
     * @param string $hash
     * @return string
     */
    private function getCacheKey(string $hash): string
    {
        return self::CACHE_KEY_PREFIX . $hash;
    }

    /**
     * Get cache TTL from config
     *
     * @return int
     */
    private function getCacheTtl(): int
    {
        $ttl = (int) $this->scopeConfig->getValue('imagesearch/general/cache_ttl');
        return $ttl > 0 ? $ttl * 60 : self::DEFAULT_TTL;
    }
}