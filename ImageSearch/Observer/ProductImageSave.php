<?php
declare(strict_types=1);

namespace Vendor\ImageSearch\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Vendor\ImageSearch\Queue\Publisher;
use Psr\Log\LoggerInterface;

class ProductImageSave implements ObserverInterface
{
    /**
     * @param Publisher $publisher
     * @param LoggerInterface $logger
     * @param ProductRepositoryInterface $productRepository
     */
    public function __construct(
        private Publisher $publisher,
        private LoggerInterface $logger,
        private ProductRepositoryInterface $productRepository
    ) {}

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        /** @var Product $product */
        $product = $observer->getEvent()->getProduct();
        
        if (!$product->getSku()) {
            return;
        }

        try {
            // Перезагружаем продукт, чтобы получить все данные галереи
            $reloadedProduct = $this->productRepository->getById($product->getId());
            $images = $this->extractProductImages($reloadedProduct);
            
            if (empty($images)) {
                return;
            }

            $message = json_encode([
                'sku' => $product->getSku(),
                'images' => $images,
                'store_id' => $product->getStoreId(),
                'category_ids' => $product->getCategoryIds()
            ]);

            $this->publisher->execute('image.search.index.product', $message);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to queue product images: ' . $e->getMessage());
        }
    }

    /**
     * Extract images from product using proper Magento 2 methods
     *
     * @param Product $product
     * @return array
     */
    private function extractProductImages(Product $product): array
    {
        $images = [];
        
        // Правильный способ получить изображения в Magento 2.4.8
        // Используем getMediaGalleryEntries() вместо getMediaGalleryImages()
        $galleryEntries = $product->getMediaGalleryEntries();
        
        if (!$galleryEntries || !is_array($galleryEntries)) {
            // Пробуем альтернативный метод, если entries пустые
            $galleryImages = $product->getMediaGalleryImages();
            if ($galleryImages && $galleryImages->getSize() > 0) {
                foreach ($galleryImages as $image) {
                    $images[] = [
                        'file' => $image->getFile(),
                        'url' => $image->getUrl(),
                        'role' => $this->getImageRole($product, $image->getFile()),
                        'position' => (int)$image->getPosition() ?: 0
                    ];
                }
            }
            return $images;
        }

        foreach ($galleryEntries as $entry) {
            $imageData = $entry->getData();
            
            // Получаем типы изображений (base, small, thumbnail)
            $types = $entry->getTypes() ?: [];
            $role = !empty($types) ? implode(',', $types) : 'gallery';
            
            $images[] = [
                'file' => $entry->getFile(),
                'url' => $this->getImageUrl($product, $entry->getFile()),
                'role' => $role,
                'position' => (int)$entry->getPosition() ?: 0,
                'disabled' => (bool)$entry->isDisabled()
            ];
        }
        
        return $images;
    }

    /**
     * Get image role based on product's image types
     *
     * @param Product $product
     * @param string $file
     * @return string
     */
    private function getImageRole(Product $product, string $file): string
    {
        $roles = [];
        if ($product->getImage() == $file) {
            $roles[] = 'base';
        }
        if ($product->getSmallImage() == $file) {
            $roles[] = 'small';
        }
        if ($product->getThumbnail() == $file) {
            $roles[] = 'thumbnail';
        }
        
        return !empty($roles) ? implode(',', $roles) : 'gallery';
    }

    /**
     * Get full image URL
     *
     * @param Product $product
     * @param string $file
     * @return string
     */
    private function getImageUrl(Product $product, string $file): string
    {
        // Базовая URL для изображений
        return $product->getMediaConfig()->getMediaUrl($file);
    }
}