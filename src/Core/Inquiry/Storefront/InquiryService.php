<?php declare(strict_types=1);

namespace CottonArt\Inquiry\Core\Inquiry\Storefront;

use Shopware\Core\Checkout\Cart\AbstractCartPersister;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class InquiryService
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly AbstractCartPersister $cartPersister,
        private readonly OrderService $orderService
    ) {
    }

    public function addInquiryItems($items, Request $request, SalesChannelContext $context): Cart
    {
        $cart = $this->getInquiryCart($request, $context);

        $cart = $this->cartService->add($cart, $items, $context);

        return $cart;
    }

    public function getInquiryCart(Request $request, SalesChannelContext $context): Cart
    {
        $session = $request->getSession();

        $inquiryCartToken = $session->get('inquiry.cartToken');
        if ($inquiryCartToken === null) {
            $inquiryCartToken = Uuid::randomHex();
            $session->set('inquiry.cartToken', $inquiryCartToken);
            $cart = $this->cartService->createNew($inquiryCartToken);

        } else {
            $cart = $this->cartService->getCart($inquiryCartToken, $context);
        }

        $this->activateInquiryMode($cart);

        return $cart;
    }

    public function createInquiry(RequestDataBag $data, Request $request, SalesChannelContext $context): string
    {
        // Backup current cart
        $cart = $this->cartService->getCart($context->getToken(), $context);

        $inquiryCartToken = $request->getSession()->get('inquiry.cartToken');

        $inquiryCart = $this->getInquiryCart($request, $context);
        $inquiryCart->setToken($context->getToken());
        $this->cartService->setCart($inquiryCart);
        $this->cartPersister->save($inquiryCart, $context);
        $this->cartPersister->delete($inquiryCartToken, $context);

        $orderId = $this->orderService->createOrder($data, $context);

        $cart->setToken($context->getToken());
        $this->cartService->setCart($cart);
        $this->cartPersister->save($cart, $context);

        return $orderId;
    }

    protected function activateInquiryMode($cart): void
    {
        $cart->addExtension('inquiry', new ArrayEntity([
            'status' => true
        ]));
    }
}