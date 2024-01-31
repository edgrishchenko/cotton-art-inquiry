<?php declare(strict_types=1);

namespace CottonArt\Inquiry\Subscriber;

use CottonArt\Inquiry\CottonArtInquiry;
use CottonArt\Inquiry\Service\InquiryPayment;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class InquiryConfirmSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityRepository $paymentMethodRepository,
        private readonly EntityRepository $shippingMethodRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutConfirmPageLoadedEvent::class => 'onConfirmPageLoaded'
        ];
    }

    public function onConfirmPageLoaded(CheckoutConfirmPageLoadedEvent $event): void
    {
        $event->getPage()->getPaymentMethods()->remove($this->getInquiryPaymentMethodId($event->getSalesChannelContext()));
        $event->getPage()->getShippingMethods()->remove($this->getInquiryShippingMethodId($event->getSalesChannelContext()));
    }

    private function getInquiryPaymentMethodId(SalesChannelContext $context): string
    {
        $criteria = (new Criteria())->addFilter(new EqualsFilter('handlerIdentifier', InquiryPayment::class));
        return $this->paymentMethodRepository->searchIds($criteria, $context->getContext())->firstId();
    }

    private function getInquiryShippingMethodId(SalesChannelContext $context): string
    {
        $criteria = new Criteria([CottonArtInquiry::SHIPPING_METHOD_ID]);
        return $this->shippingMethodRepository->searchIds($criteria, $context->getContext())->firstId();
    }
}
