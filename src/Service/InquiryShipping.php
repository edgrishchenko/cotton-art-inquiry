<?php declare(strict_types=1);

namespace CottonArt\Inquiry\Service;

use CottonArt\Inquiry\CottonArtInquiry;
use Shopware\Core\Checkout\Cart\AbstractCartPersister;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartCalculator;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class InquiryShipping
{
    public function __construct (
        private readonly EntityRepository $shippingMethodRepository,
        private readonly CartService $cartService,
        private readonly CartCalculator $calculator,
        private readonly AbstractCartPersister $cartPersister
    ) {
    }

    public function updateCartShipping(string $token, SalesChannelContext $context): Cart {
        $originalCart = $this->cartService->getCart($token, $context);

        if (in_array('inquiry', $context->getStates())) {
            $criteria = new Criteria([CottonArtInquiry::SHIPPING_METHOD_ID]);
            $shippingMethod = $this->shippingMethodRepository->search($criteria, $context->getContext())->first();

            $context->assign([
                'shippingMethod' => $shippingMethod,
            ]);

            $newCart = $this->calculator->calculate($originalCart, $context);

            $this->cartPersister->save($newCart, $context);

            return $newCart;
        }

        return $originalCart;
    }
}