<?php
declare(strict_types=1);

namespace Vendor\ImageSearch\Controller\Search;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Filesystem;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Vendor\ImageSearch\Api\SearchServiceInterface;
use Vendor\ImageSearch\Model\CacheManager;
use Psr\Log\LoggerInterface;

class Image implements HttpPostActionInterface
{
    private const UPLOAD_DIR = 'imagesearch';

    /**
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @param RedirectFactory $redirectFactory
     * @param ManagerInterface $messageManager
     * @param Filesystem $filesystem
     * @param UploaderFactory $uploaderFactory
     * @param SearchServiceInterface $searchService
     * @param CacheManager $cacheManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        private RequestInterface $request,
        private ResponseInterface $response,
        private RedirectFactory $redirectFactory,
        private ManagerInterface $messageManager,
        private Filesystem $filesystem,
        private UploaderFactory $uploaderFactory,
        private SearchServiceInterface $searchService,
        private CacheManager $cacheManager,
        private LoggerInterface $logger
    ) {}

    /**
     * Execute image search
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $redirect = $this->redirectFactory->create();
        
        try {
            $imageContent = $this->getUploadedImage();
            
            if (!$imageContent) {
                $this->messageManager->addErrorMessage(__('Please upload an image.'));
                return $redirect->setPath('catalogsearch/result');
            }

            // Prepare filters from current context
            $filters = [
                'store_id' => $this->request->getParam('store', 1),
                'category_ids' => $this->request->getParam('cat', [])
            ];

            // Perform search via AI service
            $searchResult = $this->searchService->searchByImage($imageContent, $filters);
            
            if (empty($searchResult['hits']['hits'])) {
                $this->messageManager->addNoticeMessage(__('No similar products found.'));
                return $redirect->setPath('catalogsearch/result');
            }

            // Extract product IDs and chips
            $productIds = [];
            $chips = [];
            
            foreach ($searchResult['hits']['hits'] as $hit) {
                $productIds[] = (int) $hit['_source']['product_id'];
            }
            
            if (isset($searchResult['chips'])) {
                $chips = $searchResult['chips'];
            }

            // Generate unique hash for this search
            $hash = md5($imageContent . json_encode($filters));
            
            // Cache results
            $this->cacheManager->saveResults($hash, [
                'product_ids' => $productIds,
                'chips' => $chips,
                'meta' => [
                    'store_id' => $filters['store_id'],
                    'created_at' => time()
                ]
            ]);

            // Redirect to search results with hash
            return $redirect->setPath('catalogsearch/result', [
                'q' => 'img:' . $hash,
                '_query' => $this->request->getParams()
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Image search controller error: ' . $e->getMessage());
            $this->messageManager->addErrorMessage(__('An error occurred during image search.'));
            return $redirect->setPath('catalogsearch/result');
        }
    }

    /**
     * Get uploaded image content
     *
     * @return string|null Base64 encoded image
     */
    private function getUploadedImage(): ?string
    {
        try {
            $uploader = $this->uploaderFactory->create(['fileId' => 'image']);
            $uploader->setAllowedExtensions(['jpg', 'jpeg', 'png', 'gif']);
            $uploader->setAllowRenameFiles(true);
            
            $mediaDirectory = $this->filesystem->getDirectoryWrite(\Magento\Framework\App\Filesystem\DirectoryList::MEDIA);
            $targetPath = $mediaDirectory->getAbsolutePath(self::UPLOAD_DIR);
            
            $result = $uploader->save($targetPath);
            
            if (isset($result['path'], $result['file'])) {
                $filePath = $result['path'] . '/' . $result['file'];
                $content = file_get_contents($filePath);
                
                // Clean up uploaded file
                unlink($filePath);
                
                return base64_encode($content);
            }
            
        } catch (\Exception $e) {
            $this->logger->error('File upload failed: ' . $e->getMessage());
        }
        
        return null;
    }
}