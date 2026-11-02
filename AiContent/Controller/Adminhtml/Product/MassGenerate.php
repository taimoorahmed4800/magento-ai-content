<?php
declare(strict_types=1);

namespace Taimoor\AiContent\Controller\Adminhtml\Product;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Magento\Ui\Component\MassAction\Filter;
use Psr\Log\LoggerInterface;
use Taimoor\AiContent\Model\ContentGenerator;

/**
 * Mass action in the product grid: "Generate AI Content".
 * Works synchronously, so the number of products per run is limited.
 */
class MassGenerate extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Taimoor_AiContent::generate';

    private const LIMIT = 10;

    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly ContentGenerator $generator,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $redirect = $this->resultRedirectFactory->create()->setPath('catalog/product/index');

        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            $allIds     = array_map('intval', $collection->getAllIds());
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            return $redirect;
        }

        $ids = array_slice($allIds, 0, self::LIMIT);

        if (count($allIds) > self::LIMIT) {
            $this->messageManager->addNoticeMessage(
                __('Only the first %1 of %2 selected products were processed. Run it again for the rest.', self::LIMIT, count($allIds))
            );
        }

        $done = 0;
        $failed = 0;

        foreach ($ids as $id) {
            try {
                $this->generator->generateById($id);
                $done++;
            } catch (\Throwable $e) {
                $failed++;
                $this->logger->error('AiContent: product ' . $id . ': ' . $e->getMessage());
            }
        }

        if ($done) {
            $this->messageManager->addSuccessMessage(__('AI content generated for %1 product(s).', $done));
        }

        if ($failed) {
            $this->messageManager->addErrorMessage(__('%1 product(s) failed. Please check var/log for details.', $failed));
        }

        return $redirect;
    }
}
