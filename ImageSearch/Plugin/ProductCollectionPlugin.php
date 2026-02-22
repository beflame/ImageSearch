<?php
declare(strict_types=1);

namespace Vendor\ImageSearch\Plugin;

use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection;
use Magento\Framework\App\RequestInterface;
use Vendor\ImageSearch\Model\CacheManager;
use Magento\Framework\DB\Select;

class ProductCollectionPlugin
{
    private const IMG_QUERY_PREFIX = 'img:';

    /**
     * @param RequestInterface $request
     * @param CacheManager $cacheManager
     */
    public function __construct(
        private RequestInterface $request,
        private CacheManager $cacheManager
    ) {}

    /**
     * Override collection for image search queries
     *
     * @param Collection $subject
     * @param bool $printQuery
     * @param bool $logQuery
     * @return array
     */
    public function beforeLoad(Collection $subject, $printQuery = false, $logQuery = false)
    {
        $query = $this->request->getParam('q');
        
        if ($query && str_starts_with($query, self::IMG_QUERY_PREFIX)) {
            $hash = substr($query, strlen(self::IMG_QUERY_PREFIX));
            $cachedData = $this->cacheManager->loadResults($hash);
            
            if ($cachedData && isset($cachedData['product_ids'])) {
                $productIds = $cachedData['product_ids'];
                $pageSize = $subject->getPageSize();
                $curPage = $subject->getCurPage() ?: 1;
                
                // Apply pagination to cached IDs
                $offset = ($curPage - 1) * $pageSize;
                $paginatedIds = array_slice($productIds, $offset, $pageSize);
                
                if (!empty($paginatedIds)) {
                    // Apply filter and preserve order
                    $subject->addFieldToFilter('entity_id', ['in' => $paginatedIds]);
                    $subject->getSelect()->order(
                        new \Zend_Db_Expr('FIELD(e.entity_id,' . implode(',', $paginatedIds) . ')')
                    );
                } else {
                    // Force empty result for out-of-range pages
                    $subject->addFieldToFilter('entity_id', ['in' => [0]]);
                }
                
                // Store chips data for later use in layout
                if (isset($cachedData['chips'])) {
                    $subject->setData('imagesearch_chips', $cachedData['chips']);
                }
            }
        }
        
        return [$printQuery, $logQuery];
    }
}