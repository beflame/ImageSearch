<?php
namespace Vendor\ImageSearch\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Vendor\ImageSearch\Queue\Publisher;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;

class IndexImages extends Command
{
    protected $publisher;
    protected $productCollectionFactory;

    public function __construct(
        Publisher $publisher,
        CollectionFactory $productCollectionFactory,
        $name = null
    ) {
        $this->publisher = $publisher;
        $this->productCollectionFactory = $productCollectionFactory;
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setName('vendor:imagesearch:index')
             ->setDescription('Index all product images for visual search');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $collection = $this->productCollectionFactory->create();
        $collection->addAttributeToSelect('*');
        
        $total = $collection->getSize();
        $output->writeln("Found {$total} products to index");
        
        $count = 0;
        foreach ($collection as $product) {
            $images = [];
            $galleryImages = $product->getMediaGalleryImages();
            
            foreach ($galleryImages as $image) {
                $images[] = [
                    'file' => $image->getFile(),
                    'url' => $image->getUrl(),
                    'role' => $this->getImageRole($product, $image->getFile()),
                    'position' => (int)$image->getPosition()
                ];
            }
            
            if (!empty($images)) {
                $this->publisher->execute('image.search.index.product', json_encode([
                    'sku' => $product->getSku(),
                    'images' => $images,
                    'store_id' => $product->getStoreId(),
                    'category_ids' => $product->getCategoryIds()
                ]));
                $count++;
            }
            
            if ($count % 100 == 0) {
                $output->writeln("Queued {$count} products");
            }
        }
        
        $output->writeln("Successfully queued {$count} products");
        return Command::SUCCESS;
    }
    
    private function getImageRole($product, $file)
    {
        $roles = [];
        if ($product->getImage() == $file) $roles[] = 'base';
        if ($product->getSmallImage() == $file) $roles[] = 'small';
        if ($product->getThumbnail() == $file) $roles[] = 'thumbnail';
        return !empty($roles) ? implode(',', $roles) : 'gallery';
    }
}