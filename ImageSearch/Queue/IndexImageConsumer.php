<?php
declare(strict_types=1);

namespace Vendor\ImageSearch\Queue;

use Vendor\ImageSearch\Api\SearchServiceInterface;
use Psr\Log\LoggerInterface;
use Magento\Framework\MessageQueue\ConsumerConfiguration;
use Magento\Framework\MessageQueue\EnvelopeInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Webapi\Exception;

class IndexImageConsumer
{
    /**
     * @var SearchServiceInterface
     */
    private $searchService;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param SearchServiceInterface $searchService
     * @param LoggerInterface $logger
     */
    public function __construct(
        SearchServiceInterface $searchService,
        LoggerInterface $logger
    ) {
        $this->searchService = $searchService;
        $this->logger = $logger;
    }

    /**
     * Process queue message
     *
     * @param EnvelopeInterface $message
     * @return void
     * @throws LocalizedException
     */
    public function process(EnvelopeInterface $message): void
    {
        $topicName = $message->getProperties()['topic_name'] ?? 'unknown';
        $this->logger->info('Processing message', [
            'topic' => $topicName,
            'message_id' => $message->getProperties()['message_id'] ?? 'unknown'
        ]);

        try {
            $body = $message->getBody();
            $data = json_decode($body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \InvalidArgumentException('Invalid JSON message: ' . json_last_error_msg());
            }

            if (!isset($data['sku'], $data['images'])) {
                throw new \InvalidArgumentException('Missing required fields: sku and images');
            }

            $this->logger->info('Indexing product images', [
                'sku' => $data['sku'],
                'image_count' => count($data['images'])
            ]);

            $result = $this->searchService->indexProductImages(
                $data['sku'],
                $data['images']
            );

            if (($result['status'] ?? '') !== 'ok') {
                $errorMsg = $result['message'] ?? 'Unknown error';
                $this->logger->error('Indexing failed', [
                    'sku' => $data['sku'],
                    'error' => $errorMsg
                ]);
                throw new LocalizedException(__('Indexing failed: %1', $errorMsg));
            }

            $this->logger->info('Successfully indexed product', [
                'sku' => $data['sku']
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Consumer error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new LocalizedException(__($e->getMessage()));
        }
    }
}