<?php
declare(strict_types=1);

namespace Taimoor\AiContent\Controller\Adminhtml\Product;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use Taimoor\AiContent\Model\ContentGenerator;

/**
 * "Generate AI Content" button on the product edit page.
 */
class Generate extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Taimoor_AiContent::generate';

    public function __construct(
        Context $context,
        private readonly ContentGenerator $generator,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $productId = (int) $this->getRequest()->getParam('id');

        try {
            $result = $this->generator->generateById($productId);

            if ($result['updated']) {
                $this->messageManager->addSuccessMessage(
                    __('AI content saved for fields: %1.', implode(', ', array_keys($result['updated'])))
                );
            }

            if ($result['skipped']) {
                $this->messageManager->addNoticeMessage(
                    __('Skipped (already has content): %1. Enable "Overwrite Existing Content" to replace it.', implode(', ', $result['skipped']))
                );
            }
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Throwable $e) {
            $this->logger->error('AiContent: ' . $e->getMessage());
            $this->messageManager->addErrorMessage(__('AI generation failed. Please check var/log for details.'));
        }

        return $this->resultRedirectFactory->create()->setPath('catalog/product/edit', ['id' => $productId]);
    }
}
