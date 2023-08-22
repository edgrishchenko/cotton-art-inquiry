<?php declare(strict_types=1);

namespace Pix\Inquiry\Service;

use Pix\Inquiry\PixInquiry;
use Shopware\Core\Checkout\Cart\AbstractCartPersister;
use Shopware\Core\Checkout\Cart\CartCalculator;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Shipping\SalesChannel\AbstractShippingMethodRoute;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannel\AbstractContextSwitchRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class InquiryShipping
{
    public function __construct (
        private readonly EntityRepository $shippingMethodRepository,
        private readonly AbstractShippingMethodRoute $shippingMethodRoute,
        private readonly CartService $cartService,
        private readonly AbstractContextSwitchRoute $contextSwitchRoute,
        private readonly CartCalculator $calculator,
        private readonly AbstractCartPersister $cartPersister
    ) {
    }

    public function updateCartShipping(string $token, SalesChannelContext $originalContext): void {
        $originalCart = $this->cartService->getCart($token, $originalContext);

        $criteria = new Criteria([PixInquiry::SHIPPING_METHOD_ID]);
        $shippingMethod = $this->shippingMethodRepository->search($criteria, $originalContext->getContext())->first();

        if (!in_array('inquiry', $originalContext->getStates())) {
            $request = new Request(['onlyAvailable' => true]);
            $defaultShippingMethod = $this->shippingMethodRoute->load(
                $request,
                $originalContext,
                new Criteria([$originalContext->getSalesChannel()->getShippingMethodId()])
            )->getShippingMethods()->first();

            $shippingMethod = $defaultShippingMethod;
        }

        $updatedContext = clone $originalContext;
        $updatedContext->assign([
            'shippingMethod' => $shippingMethod,
        ]);

        $newCart = $this->calculator->calculate($originalCart, $updatedContext);

        $this->cartPersister->save($newCart, $updatedContext);
        $this->updateSalesChannelContext($updatedContext);
    }

    public function updateSalesChannelContext(SalesChannelContext $salesChannelContext): void
    {
        $this->contextSwitchRoute->switchContext(
            new RequestDataBag([
                SalesChannelContextService::SHIPPING_METHOD_ID => $salesChannelContext->getShippingMethod()->getId(),
            ]),
            $salesChannelContext
        );
    }
}