<?php
declare(strict_types=1);

namespace Taimoor\AiContent\Block\Adminhtml\Product\Edit\Button;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

/**
 * Adds the "Generate AI Content" button to the product edit page.
 */
class Generate implements ButtonProviderInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly UrlInterface $urlBuilder,
        private readonly AuthorizationInterface $authorization
    ) {
    }

    public function getButtonData(): array
    {
        $productId = (int) $this->request->getParam('id');

        // Only for existing products and only for allowed admin users
        if (!$productId || !$this->authorization->isAllowed('Taimoor_AiContent::generate')) {
            return [];
        }

        $url = $this->urlBuilder->getUrl('taimoor_aicontent/product/generate', ['id' => $productId]);

        return [
            'label'      => __('Generate AI Content'),
            'class'      => 'action-secondary',
            'on_click'   => "if (confirm('The page will reload and unsaved changes will be lost. Continue?')) { setLocation('" . $url . "'); }",
            'sort_order' => 45,
        ];
    }
}
