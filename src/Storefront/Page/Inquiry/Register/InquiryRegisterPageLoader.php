<?php declare(strict_types=1);

namespace CottonArt\Inquiry\Storefront\Page\Inquiry\Register;

use CottonArt\Inquiry\Core\Inquiry\Storefront\InquiryService;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractListAddressRoute;
use Shopware\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Uuid\UuidException;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Country\SalesChannel\AbstractCountryRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Salutation\SalesChannel\AbstractSalutationRoute;
use Shopware\Core\System\Salutation\SalutationCollection;
use Shopware\Core\System\Salutation\SalutationEntity;
use Shopware\Storefront\Page\Checkout\Register\CheckoutRegisterPage;
use Shopware\Storefront\Page\Checkout\Register\CheckoutRegisterPageLoadedEvent;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Shopware\Storefront\Page\MetaInformation;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class InquiryRegisterPageLoader
{
    public function __construct(
        private readonly GenericPageLoaderInterface $genericLoader,
        private readonly AbstractListAddressRoute $listAddressRoute,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly InquiryService $inquiryService,
        private readonly AbstractSalutationRoute $salutationRoute,
        private readonly AbstractCountryRoute $countryRoute,
        private readonly ?AbstractTranslator $translator = null
    ) {
    }

    public function load(Request $request, SalesChannelContext $salesChannelContext): CheckoutRegisterPage
    {
        $page = $this->genericLoader->load($request, $salesChannelContext);

        $page = CheckoutRegisterPage::createFrom($page);
        $this->setMetaInformation($page);

        $page->setCountries($this->getCountries($salesChannelContext));
        $page->setCart($this->inquiryService->getInquiryCart($request, $salesChannelContext));
        $page->setSalutations($this->getSalutations($salesChannelContext));

        $addressId = $request->attributes->get('addressId');
        if ($addressId) {
            $address = $this->getById((string) $addressId, $salesChannelContext);
            $page->setAddress($address);
        }

        $this->eventDispatcher->dispatch(
            new CheckoutRegisterPageLoadedEvent($page, $salesChannelContext, $request)
        );

        return $page;
    }

    protected function setMetaInformation(CheckoutRegisterPage $page): void
    {
        /**
         * @deprecated tag:v6.7.0 - Remove condition in 6.7.
         */
        if ($page->getMetaInformation()) {
            $page->getMetaInformation()->setRobots('noindex,follow');
        }

        /**
         * @deprecated tag:v6.7.0 - Remove condition with body in 6.7.
         */
        if ($this->translator !== null && $page->getMetaInformation() === null) {
            $page->setMetaInformation(new MetaInformation());
        }

        if ($this->translator !== null) {
            $page->getMetaInformation()?->setMetaTitle(
                $this->translator->trans('checkout.registerMetaTitle') . ' | ' . $page->getMetaInformation()->getMetaTitle()
            );
        }
    }

    private function getById(string $addressId, SalesChannelContext $context): CustomerAddressEntity
    {
        if (!Uuid::isValid($addressId)) {
            throw UuidException::invalidUuid($addressId);
        }

        if ($context->getCustomer() === null) {
            throw CartException::customerNotLoggedIn();
        }
        $customer = $context->getCustomer();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $addressId));
        $criteria->addFilter(new EqualsFilter('customerId', $customer->getId()));

        $address = $this->listAddressRoute->load($criteria, $context, $customer)->getAddressCollection()->get($addressId);

        if (!$address) {
            throw CustomerException::addressNotFound($addressId);
        }

        return $address;
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    private function getSalutations(SalesChannelContext $salesChannelContext): SalutationCollection
    {
        $salutations = $this->salutationRoute->load(new Request(), $salesChannelContext, new Criteria())->getSalutations();

        $salutations->sort(fn (SalutationEntity $a, SalutationEntity $b) => $b->getSalutationKey() <=> $a->getSalutationKey());

        return $salutations;
    }

    private function getCountries(SalesChannelContext $salesChannelContext): CountryCollection
    {
        $countries = $this->countryRoute->load(new Request(), new Criteria(), $salesChannelContext)->getCountries();

        $countries->sortCountryAndStates();

        return $countries;
    }
}