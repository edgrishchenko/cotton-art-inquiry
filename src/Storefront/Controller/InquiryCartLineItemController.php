<?php declare(strict_types=1);

namespace CottonArt\Inquiry\Storefront\Controller;

use CottonArt\Inquiry\Core\Inquiry\Storefront\InquiryService;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Cart\LineItemFactoryRegistry;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Promotion\Cart\PromotionCartAddedInformationError;
use Shopware\Core\Checkout\Promotion\Cart\PromotionItemBuilder;
use Shopware\Core\Content\Product\Exception\ProductNotFoundException;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class InquiryCartLineItemController extends StorefrontController
{
    public function __construct(
        private readonly LineItemFactoryRegistry $lineItemFactoryRegistry,
        private readonly InquiryService $inquiryService,
        private readonly CartService $cartService,
        private readonly PromotionItemBuilder $promotionItemBuilder
    ) {
    }

    #[Route(path: '/inquiry/inquiry-line-item/add', name: 'frontend.inquiry.inquiry-line-item.add', defaults: ['XmlHttpRequest' => true], methods: ['POST'])]
    public function addLineItems(Cart $cart, RequestDataBag $requestDataBag, Request $request, SalesChannelContext $context): Response
    {
        $lineItems = $requestDataBag->get('lineItems');
        if (!$lineItems) {
            throw RoutingException::missingRequestParameter('lineItems');
        }

        $count = 0;

        try {
            $items = [];
            /** @var RequestDataBag $lineItemData */
            foreach ($lineItems as $lineItemData) {
                try {
                    $item = $this->lineItemFactoryRegistry->create($this->getLineItemArray($lineItemData, [
                        'quantity' => 1,
                        'stackable' => true,
                        'removable' => true,
                    ]), $context);
                    $count += $item->getQuantity();

                    $items[] = $item;
                } catch (CartException $e) {
                    if ($e->getErrorCode() === CartException::CART_INVALID_LINE_ITEM_QUANTITY_CODE) {
                        $this->addFlash(
                            self::DANGER,
                            $this->trans(
                                'error.CHECKOUT__CART_INVALID_LINE_ITEM_QUANTITY',
                                [
                                    '%quantity%' => $e->getParameter('quantity'),
                                ]
                            )
                        );

                        return $this->createActionResponse($request);
                    }

                    throw $e;
                }
            }

            $cart = $this->inquiryService->addInquiryItems($items, $request, $context);

            if (!$this->traceErrors($cart)) {
                $this->addFlash(self::SUCCESS, $this->trans('checkout.addToCartSuccess', ['%count%' => $count]));
            }
        } catch (ProductNotFoundException|RoutingException) {
            $this->addFlash(self::DANGER, $this->trans('error.addToCartError'));
        }

        return $this->createActionResponse($request);
    }

    #[Route(path: '/inquiry/inquiry-line-item/change-quantity/{id}', name: 'frontend.inquiry.inquiry-line-item.change-quantity', defaults: ['XmlHttpRequest' => true], methods: ['POST'])]
    public function changeQuantity(string $id, Request $request, SalesChannelContext $context): Response
    {
        try {
            $cart = $this->inquiryService->getInquiryCart($request, $context);

            $quantity = $request->get('quantity');

            if ($quantity === null) {
                throw RoutingException::missingRequestParameter('quantity');
            }

            if (!$cart->has($id)) {
                throw CartException::lineItemNotFound($id);
            }

            $cart = $this->cartService->changeQuantity($cart, $id, (int)$quantity, $context);

            if (!$this->traceErrors($cart)) {
                $this->addFlash(self::SUCCESS, $this->trans('checkout.cartUpdateSuccess'));
            }
        } catch (\Exception) {
            $this->addFlash(self::DANGER, $this->trans('error.message-default'));
        }

        return $this->createActionResponse($request);
    }

    #[Route(path: '/inquiry/inquiry-line-item/delete/{id}', name: 'frontend.inquiry.inquiry-line-item.delete', defaults: ['XmlHttpRequest' => true], methods: ['POST', 'DELETE'])]
    public function deleteLineItem(string $id, Request $request, SalesChannelContext $context): Response
    {
        try {
            $cart = $this->inquiryService->getInquiryCart($request, $context);

            if (!$cart->has($id)) {
                throw CartException::lineItemNotFound($id);
            }

            $cart = $this->cartService->remove($cart, $id, $context);

            if (!$this->traceErrors($cart)) {
                $this->addFlash(self::SUCCESS, $this->trans('checkout.cartUpdateSuccess'));
            }
        } catch (\Exception) {
            $this->addFlash(self::DANGER, $this->trans('error.message-default'));
        }

        return $this->createActionResponse($request);
    }

    #[Route(path: '/inquiry/promotion/add', name: 'frontend.inquiry.promotion.add', defaults: ['XmlHttpRequest' => true], methods: ['POST'])]
    public function addPromotion(Request $request, SalesChannelContext $context): Response
    {
        try {
            $cart = $this->inquiryService->getInquiryCart($request, $context);

            $code = (string)$request->request->get('code');

            if ($code === '') {
                throw RoutingException::missingRequestParameter('code');
            }

            $lineItem = $this->promotionItemBuilder->buildPlaceholderItem($code);

            $cart = $this->cartService->add($cart, $lineItem, $context);

            $addedEvents = $cart->getErrors()->filterInstance(PromotionCartAddedInformationError::class);
            if ($addedEvents->count() > 0) {
                $this->addFlash(self::SUCCESS, $this->trans('checkout.codeAddedSuccessful'));

                return $this->createActionResponse($request);
            }

            $this->traceErrors($cart);
        } catch (\Exception) {
            $this->addFlash(self::DANGER, $this->trans('error.message-default'));
        }

        return $this->createActionResponse($request);
    }

    private function traceErrors(Cart $cart): bool
    {
        if ($cart->getErrors()->count() <= 0) {
            return false;
        }

        $this->addCartErrors($cart, fn(Error $error) => $error->isPersistent());

        return true;
    }

    /**
     * @param ?array{quantity: int, stackable: bool, removable: bool} $defaultValues
     *
     * @return array<string|int, mixed>
     */
    private function getLineItemArray(RequestDataBag $lineItemData, ?array $defaultValues): array
    {
        if ($lineItemData->has('payload')) {
            $payload = $lineItemData->get('payload');

            if (mb_strlen($payload, '8bit') > (1024 * 256)) {
                throw RoutingException::invalidRequestParameter('payload');
            }

            $lineItemData->set('payload', json_decode($payload, true, 512, \JSON_THROW_ON_ERROR));
        }

        $lineItemArray = $lineItemData->all();
        if ($defaultValues !== null) {
            $lineItemArray = array_replace($defaultValues, $lineItemArray);
        }

        if (isset($lineItemArray['quantity'])) {
            $lineItemArray['quantity'] = (int)$lineItemArray['quantity'];
        }

        if (isset($lineItemArray['stackable'])) {
            $lineItemArray['stackable'] = (bool)$lineItemArray['stackable'];
        }

        if (isset($lineItemArray['removable'])) {
            $lineItemArray['removable'] = (bool)$lineItemArray['removable'];
        }

        if (isset($lineItemArray['priceDefinition']['quantity'])) {
            $lineItemArray['priceDefinition']['quantity'] = (int)$lineItemArray['priceDefinition']['quantity'];
        }

        if (isset($lineItemArray['priceDefinition']['isCalculated'])) {
            $lineItemArray['priceDefinition']['isCalculated'] = (int)$lineItemArray['priceDefinition']['isCalculated'];
        }

        return $lineItemArray;
    }
}