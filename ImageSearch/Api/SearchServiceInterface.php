<?php
declare(strict_types=1);

namespace Vendor\ImageSearch\Api;

interface SearchServiceInterface
{
    /**
     * Index product images via AI service
     *
     * @param string $sku
     * @param array $imageData
     * @return array
     */
    public function indexProductImages(string $sku, array $imageData): array;

    /**
     * Perform search by image
     *
     * @param string $imageContent Base64 encoded image or path
     * @param array $filters Magento filters (store, category, etc.)
     * @return array Search results with product IDs and chips
     */
    public function searchByImage(string $imageContent, array $filters = []): array;
}