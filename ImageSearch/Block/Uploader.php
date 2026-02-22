<?php
declare(strict_types=1);

namespace Vendor\ImageSearch\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Uploader extends Template
{
    protected $scopeConfig;

    public function __construct(
        Template\Context $context,
        ScopeConfigInterface $scopeConfig,
        array $data = []
    ) {
        $this->scopeConfig = $scopeConfig;
        parent::__construct($context, $data);
    }

    /**
     * Check if module is enabled
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        // Правильный путь к конфигурации
        return (bool)$this->scopeConfig->getValue(
            'imagesearch/general/enable_frontend_upload',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get upload URL
     *
     * @return string
     */
    public function getUploadUrl(): string
    {
        return $this->getUrl('imagesearch/search/image');
    }

    /**
     * Get max file size in bytes
     *
     * @return int
     */
    public function getMaxFileSize(): int
    {
        return 20 * 1024 * 1024; // 20MB
    }

    /**
     * Get allowed file extensions
     *
     * @return array
     */
    public function getAllowedExtensions(): array
    {
        return ['jpg', 'jpeg', 'png', 'gif'];
    }
    
    /**
     * Get form key
     *
     * @return string
     */
    public function getFormKey(): string
    {
        return $this->_layout->getBlock('formkey')->toHtml();
    }
}