<?php

declare(strict_types=1);

namespace CottonArt\Inquiry\Subscriber;

use CottonArt\Inquiry\CottonArtInquiry;
use CottonArt\Inquiry\Service\InquiryCustomFieldsManagement;
use CottonArt\Inquiry\Service\InquiryPayment;
use Shopware\Core\Checkout\Cart\Order\CartConvertedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class InquirySaveSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly EntityRepository $paymentMethodRepository,
        private readonly InquiryCustomFieldsManagement $customFieldsManagement
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
        $isInquirySaved = in_array('inquiry-saved', $event->getSalesChannelContext()->getContext()->getStates());

        if ($isInquirySaved) {
            $orderData = $event->getConvertedCart();

            $orderData['transactions'][0]['paymentMethodId'] = $this->getInquiryPaymentMethodId($event->getSalesChannelContext());

            $orderData['customFields'] = $this->parseCustomFields($orderData);

            $event->setConvertedCart($orderData);
        }
    }

    private function getInquiryPaymentMethodId(SalesChannelContext $context): string
    {
        $criteria = (new Criteria())->addFilter(new EqualsFilter('handlerIdentifier', InquiryPayment::class));
        return $this->paymentMethodRepository->searchIds($criteria, $context->getContext())->firstId();
    }

    private function parseCustomFields(array $orderData): array
    {
        $orderCustomFields = $orderData['customFields'] ?? [];
        $request = $this->requestStack->getCurrentRequest()->request;

        $customFields = $this->customFieldsManagement->getCustomFields();
        foreach ($customFields as $customField) {
            $orderCustomFields[$customField->getName()] = $customField->getType() == CustomFieldTypes::SELECT
                ? $request->all($customField->getName())
                : $request->get($customField->getName());

            if ($customField->getName() == CottonArtInquiry::CUSTOM_DELIVERY_DURATION
                && isset($orderCustomFields[$customField->getName()][0])) {
                $orderCustomFields[$customField->getName()] = $orderCustomFields[$customField->getName()][0];
            }
        }

        return $orderCustomFields;
    }
}
