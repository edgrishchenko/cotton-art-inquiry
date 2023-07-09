<?php declare(strict_types=1);

namespace Pix\Inquiry\Subscriber;

use Pix\Inquiry\Service\InquiryPayment;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\Checkout\Cart\Order\CartConvertedEvent;
use Symfony\Component\HttpFoundation\RequestStack;

class InquirySaveSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly RequestStack $requestStack,
         private readonly EntityRepository $paymentMethodRepository
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CartConvertedEvent::class => 'onCartConverted'
        ];
    }

    public function onCartConverted(CartConvertedEvent $event): void
    {
        $orderData = $event->getConvertedCart();

        $orderCustomFields = $orderData['customFields'] ?? [];

        $customInquiryFile = $this->requestStack->getCurrentRequest()->request->get('inquiryUploadedFiles');

        if ($customInquiryFile) {
            $orderCustomFields['custom_pixinquiry_file'] = $customInquiryFile;
        }

        $orderData['customFields'] = $orderCustomFields;

        $event->setConvertedCart($orderData);
    }

    private function getInquiryPaymentMethodId(SalesChannelContext $context): string
    {
        $criteria = (new Criteria())->addFilter(new EqualsFilter('handlerIdentifier', InquiryPayment::class));
        return $this->paymentMethodRepository->searchIds($criteria, $context->getContext())->firstId();
    }

}