<?php declare(strict_types=1);

namespace CottonArt\Inquiry\Storefront\Page\Inquiry\Offcanvas;

use CottonArt\Inquiry\Core\Inquiry\Storefront\InquiryService;
use Shopware\Core\Checkout\Shipping\SalesChannel\AbstractShippingMethodRoute;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Checkout\Offcanvas\OffcanvasCartPage;
use Shopware\Storefront\Page\Checkout\Offcanvas\OffcanvasCartPageLoadedEvent;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class OffcanvasInquiryCartPageLoader
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly InquiryService $inquiryService,
        private readonly GenericPageLoaderInterface $genericLoader,
        private readonly AbstractShippingMethodRoute $shippingMethodRoute
    ) {
    }

    public function load(Request $request, SalesChannelContext $salesChannelContext): OffcanvasCartPage
    {
        $page = $this->genericLoader->load($request, $salesChannelContext);

        $page = OffcanvasCartPage::createFrom($page);

        if ($page->getMetaInformation()) {
            $page->getMetaInformation()->assign(['robots' => 'noindex,follow']);
        }

        $page->setCart($this->inquiryService->getInquiryCart($request, $salesChannelContext));

        $page->setShippingMethods($this->getShippingMethods($salesChannelContext));

        $this->eventDispatcher->dispatch(
            new OffcanvasCartPageLoadedEvent($page, $salesChannelContext, $request)
        );

        return $page;
    }

    private function getShippingMethods(SalesChannelContext $context): ShippingMethodCollection
    {
        $request = new Request();
        $request->query->set('onlyAvailable', '1');

        return $this->shippingMethodRoute->load($request, $context, new Criteria())->getShippingMethods();
    }
}