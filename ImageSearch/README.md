Installation & Configuration Instructions

Follow these steps to install and configure the module:
1. Copy Module Files: Place the entire Vendor/ImageSearch folder into your Magento installation's app/code directory.
2. Run Magento Commands:cd /path/to/your/magentophp bin/magento module:enable Vendor_ImageSearchphp bin/magento setup:upgradephp bin/magento setup:di:compilephp bin/magento cache:clean 
3. Configure the AI Service: The module requires the AI Search Service (Python) as defined in the manual. Ensure it is running and accessible.
4. Configure OpenSearch: Create the index magento_image_vectors_v1 in your OpenSearch instance using the mapping provided in the manual.
5. Configure Message Queue: Set up RabbitMQ or another message queue system in Magento and configure the consumer:php bin/magento queue:consumers:start imageSearch.indexImage 
6. Configure Module in Admin: Navigate to Stores → Configuration → Vendor Extensions → AI Image Search and set:
	- AI Service Base URL: The URL where your Python AI service is running (e.g., http://ai-service:8000).
	- Cache TTL: How long to cache search results.
	- Enable Frontend Upload: Set to "Yes" to show the upload form on the search results page.

⚙️ How It Works

1. Indexing: When a product is saved, the ProductImageSave observer sends a message to the queue. The consumer then calls your AI service's indexing endpoint to generate and store vectors in OpenSearch.
2. Search: A user uploads an image via the frontend form. The Image controller sends the image to the AI service, receives product IDs and optional "chips," caches the result under a unique hash, and redirects to the standard search results page with a q=img:{HASH} parameter.
3. Rendering: The ProductCollectionPlugin intercepts the search query. If it starts with img:, it loads the cached product IDs, applies pagination, and modifies the product collection query to return results in the exact order from the AI service. The Chips block renders any refinement options returned by the AI.
This module provides a complete, production-ready foundation for visual search in Magento 2.4.8, following the architecture and best practices outlined in the ntmhung/magento-ai manual.