<?php declare(strict_types=1);

namespace Pix\Inquiry\Subscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\Checkout\Cart\Order\CartConvertedEvent;
use Symfony\Component\HttpFoundation\RequestStack;

class InquirySaveSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly RequestStack $requestStack
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

        $customInquiryFile = $this->requestStack->getCurrentRequest()->request->get('inquiryUploadFile');

        if ($customInquiryFile) {
            $orderCustomFields['custom_inquiry_file'] = $customInquiryFile;
        }

        $orderData['customFields'] = $orderCustomFields;

        $event->setConvertedCart($orderData);
    }
}