<?php declare(strict_types=1);

namespace Pix\Inquiry\Subscriber;

use Pix\Inquiry\Service\InquiryShipping;
use Shopware\Storefront\Page\Checkout\Register\CheckoutRegisterPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class InquiryRegisterSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly InquiryShipping $inquiryShipping
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutRegisterPageLoadedEvent::class => 'onRegisterPageLoaded'
        ];
    }

    public function onRegisterPageLoaded(CheckoutRegisterPageLoadedEvent $event): void
    {
        $this->inquiryShipping->updateCartShipping($event->getSalesChannelContext()->getToken(), $event->getSalesChannelContext());
    }
}