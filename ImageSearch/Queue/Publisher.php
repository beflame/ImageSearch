<?php
declare(strict_types=1);

namespace Vendor\ImageSearch\Queue;

use Magento\Framework\MessageQueue\PublisherInterface;

class Publisher
{
    /**
     * @var PublisherInterface
     */
    private $publisher;

    /**
     * @param PublisherInterface $publisher
     */
    public function __construct(
        PublisherInterface $publisher
    ) {
        $this->publisher = $publisher;
    }

    /**
     * Publish message to queue
     *
     * @param string $topic
     * @param string $message
     * @return mixed|null
     */
    public function execute(string $topic, string $message)
    {
        try {
            return $this->publisher->publish($topic, $message);
        } catch (\Exception $e) {
            // Логирование ошибки
            error_log('Publisher error: ' . $e->getMessage());
            throw $e;
        }
    }
}