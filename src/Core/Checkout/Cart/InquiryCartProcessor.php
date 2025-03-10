<?php declare(strict_types=1);

namespace CottonArt\Inquiry\Core\Checkout\Cart;

use CottonArt\Inquiry\CottonArtInquiry;
use CottonArt\Inquiry\Service\InquiryPayment;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryBuilder;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RequestStack;

class InquiryCartProcessor implements CartProcessorInterface
{
    public function __construct(
        protected readonly EntityRepository $shippingMethodRepository,
        protected readonly EntityRepository $paymentMethodRepository,
        protected readonly DeliveryBuilder $deliveryBuilder,
        protected readonly RequestStack $requestStack,
    ) {
    }

    public function process(CartDataCollection $data, Cart $original, Cart $toCalculate, SalesChannelContext $context, CartBehavior $behavior): void
    {
        if ($toCalculate->getExtension('inquiry')?->get('status') === true) {
            $context->assign([
                'paymentMethod' => $this->getInquiryPaymentMethod($context)
            ]);

            $deliveries = $this->deliveryBuilder->build($toCalculate, $data, $context, $behavior);
            $delivery = $deliveries->first();

            $inquiryShippingMethod = $this->getInquiryShippingMethod($context);
            $delivery->setShippingMethod($inquiryShippingMethod);

            $toCalculate->setDeliveries($deliveries);
        }
    }

    private function getInquiryShippingMethod(SalesChannelContext $context): ShippingMethodEntity
    {
        $criteria = new Criteria([CottonArtInquiry::SHIPPING_METHOD_ID]);
        
        return $this->shippingMethodRepository->search($criteria, $context->getContext())->first();
    }

    private function getInquiryPaymentMethod(SalesChannelContext $context): PaymentMethodEntity
    {
        $criteria = (new Criteria())->addFilter(new EqualsFilter('handlerIdentifier', InquiryPayment::class));
        
        return $this->paymentMethodRepository->search($criteria, $context->getContext())->first();
    }
}