<?php declare(strict_types=1);

namespace Pix\Inquiry\Storefront\Controller;

use Pix\Inquiry\Service\FileUploader;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\Exception\InvalidCartException;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractLogoutRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AbstractRegisterRoute;
use Shopware\Core\Checkout\Order\Exception\EmptyCartException;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
use Shopware\Core\Checkout\Payment\Exception\InvalidOrderException;
use Shopware\Core\Checkout\Payment\Exception\PaymentProcessException;
use Shopware\Core\Checkout\Payment\Exception\UnknownPaymentMethodException;
use Shopware\Core\Checkout\Payment\PaymentService;
use Shopware\Core\Content\Newsletter\Exception\SalesChannelDomainNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\Profiling\Profiler;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Checkout\Cart\Error\PaymentMethodChangedError;
use Shopware\Storefront\Checkout\Cart\Error\ShippingMethodChangedError;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Storefront\Framework\AffiliateTracking\AffiliateTrackingListener;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedHook;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoader;
use Pix\Inquiry\Storefront\Page\Inquiry\Finish\InquiryFinishPageLoader;
use Shopware\Storefront\Page\Checkout\Register\CheckoutRegisterPageLoadedHook;
use Shopware\Storefront\Page\Checkout\Register\CheckoutRegisterPageLoader;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Validator\Constraints\EqualTo;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @Route(defaults={"_routeScope"={"storefront"}})
 */
class InquiryController extends StorefrontController
{
    private const REDIRECTED_FROM_SAME_ROUTE = 'redirected';

    public function __construct(
        private readonly AbstractRegisterRoute $registerRoute,
        private readonly AbstractLogoutRoute $logoutRoute,
        private readonly CheckoutRegisterPageLoader $registerPageLoader,
        private readonly CheckoutConfirmPageLoader $confirmPageLoader,
        private readonly InquiryFinishPageLoader $finishPageLoader,
        private readonly CartService $cartService,
        private readonly OrderService $orderService,
        private readonly PaymentService $paymentService,
        private readonly EntityRepository $domainRepository,
        private readonly FileUploader $fileUploader,
        private readonly SystemConfigService $systemConfigService,
    ) {
    }

    /**
     * @Route("/inquiry/register", name="frontend.inquiry.register.page", options={"seo"=false}, defaults={"_noStore"=true}, methods={"GET"})
     */
    public function inquiryRegisterPage(Request $request, RequestDataBag $data, SalesChannelContext $context): Response
    {
        $context->addState('inquiry');

        $redirect = $request->get('redirectTo', 'frontend.inquiry.confirm.page');
        $errorRoute = $request->attributes->get('_route');

        if ($context->getCustomer()) {
            return $this->redirectToRoute($redirect);
        }

        if ($this->cartService->getCart($context->getToken(), $context)->getLineItems()->count() === 0) {
            return $this->redirectToRoute('frontend.checkout.cart.page');
        }

        $page = $this->registerPageLoader->load($request, $context);

        $this->hook(new CheckoutRegisterPageLoadedHook($page, $context));

        return $this->renderStorefront(
            '@Storefront/storefront/page/checkout/address/index.html.twig',
            ['redirectTo' => $redirect, 'errorRoute' => $errorRoute, 'page' => $page, 'data' => $data, 'isInquiry' => true]
        );
    }

    /**
     * @Route("/inquiry/register", name="frontend.inquiry.register.save", defaults={"_captcha"=true}, methods={"POST"})
     */
    public function register(Request $request, RequestDataBag $data, SalesChannelContext $context): Response
    {
        if ($context->getCustomer()) {
            return $this->redirectToRoute('frontend.account.home.page');
        }

        try {
            if (!$data->has('differentShippingAddress')) {
                $data->remove('shippingAddress');
            }

            $data->set('storefrontUrl', $this->getConfirmUrl($context, $request));

            $data = $this->prepareAffiliateTracking($data, $request->getSession());

            if ($data->getBoolean('createCustomerAccount')) {
                $data->set('guest', false);
            } else {
                $data->set('guest', true);
            }

            $this->registerRoute->register(
                $data->toRequestDataBag(),
                $context,
                false,
                $this->getAdditionalRegisterValidationDefinitions($data, $context)
            );
        } catch (ConstraintViolationException $formViolations) {
            if (!$request->request->has('errorRoute')) {
                throw RoutingException::missingRequestParameter('errorRoute');
            }

            if (empty($request->request->get('errorRoute'))) {
                $request->request->set('errorRoute', 'frontend.account.register.page');
            }

            $params = $this->decodeParam($request, 'errorParameters');

            // this is to show the correct form because we have different usecases (account/register||checkout/register)
            return $this->forwardToRoute($request->get('errorRoute'), ['formViolations' => $formViolations], $params);
        }

        if ($this->isDoubleOptIn($data, $context)) {
            return $this->redirectToRoute('frontend.account.register.page');
        }

        return $this->createActionResponse($request);
    }

    /**
     * @Route("/inquiry/confirm", name="frontend.inquiry.confirm.page", options={"seo"=false}, defaults={"_noStore"=true, "XmlHttpRequest"=true}, methods={"GET"})
     */
    public function confirmPage(Request $request, SalesChannelContext $context): Response
    {
        $context->addState('inquiry');

        if (!$context->getCustomer()) {
            return $this->redirectToRoute('frontend.inquiry.register.page');
        }

        if ($this->cartService->getCart($context->getToken(), $context)->getLineItems()->count() === 0) {
            return $this->redirectToRoute('frontend.checkout.cart.page');
        }

        $allowedMimeTypes = $this->systemConfigService->get('PixInquiry.config.allowedMimeTypes', $context->getSalesChannel()->getId());

        $page = $this->confirmPageLoader->load($request, $context);
        $cart = $page->getCart();
        $cartErrors = $cart->getErrors();

        $this->hook(new CheckoutConfirmPageLoadedHook($page, $context));

        $this->addCartErrors($cart);

        if (!$request->query->getBoolean(self::REDIRECTED_FROM_SAME_ROUTE) && $this->routeNeedsReload($cartErrors)) {
            $cartErrors->clear();

            // To prevent redirect loops add the identifier that the request already got redirected from the same origin
            return $this->redirectToRoute(
                'frontend.inquiry.confirm.page',
                [...$request->query->all(), ...[self::REDIRECTED_FROM_SAME_ROUTE => true]],
            );
        }

        return $this->renderStorefront('@Storefront/storefront/page/checkout/confirm/index.html.twig',
            ['page' => $page, 'isInquiry' => true, 'allowedMimeTypes' => $allowedMimeTypes]
        );
    }

    /**
     * @Route("/inquiry/save", name="frontend.inquiry.save", options={"seo"=false}, methods={"POST"})
     */
    public function inquirySave(RequestDataBag $data, SalesChannelContext $context, Request $request): Response
    {
        if (!$context->getCustomer()) {
            return $this->redirectToRoute('frontend.inquiry.register.page');
        }

        try {
            $this->addAffiliateTracking($data, $request->getSession());

            $request->request->set('inquirySaved', true);
            $context->addState('inquiry-saved');

            $inquiryUploadFiles = $request->files->get('inquiryUploadFile');
            if (count($inquiryUploadFiles) > 0) {
                $storefrontUrl = $this->getConfirmUrl($context, $request);
                $uploadedFiles = array_map(fn($file): string => $storefrontUrl . $file, $this->fileUploader->upload($inquiryUploadFiles, $context));
                $request->request->set('inquiryUploadedFiles', implode(', ', $uploadedFiles));
            }

            $orderId = Profiler::trace('checkout-order', fn () => $this->orderService->createOrder($data, $context));
        } catch (ConstraintViolationException $formViolations) {
            return $this->forwardToRoute('frontend.inquiry.confirm.page', ['formViolations' => $formViolations]);
        } catch (InvalidCartException|Error|EmptyCartException) {
            $this->addCartErrors(
                $this->cartService->getCart($context->getToken(), $context)
            );

            return $this->forwardToRoute('frontend.inquiry.confirm.page');
        } catch (UnknownPaymentMethodException|CartException $e) {
            if ($e->getErrorCode() === CartException::CART_PAYMENT_INVALID_ORDER_STORED_CODE && $e->getParameter('orderId')) {
                return $this->forwardToRoute('frontend.inquiry.finish.page', ['orderId' => $e->getParameter('orderId'), 'changedPayment' => false, 'paymentFailed' => true]);
            }
            $message = $this->trans('error.' . $e->getErrorCode());
            $this->addFlash('danger', $message);

            return $this->forwardToRoute('frontend.inquiry.confirm.page');
        } catch (FileException $e) {
            $this->addFlash('danger', $e->getMessage());

            return $this->forwardToRoute('frontend.inquiry.confirm.page');
        }

        try {
            $finishUrl = $this->generateUrl('frontend.inquiry.finish.page', ['orderId' => $orderId]);

            $errorUrl = $this->generateUrl('frontend.account.edit-order.page', ['orderId' => $orderId]);

            $response = Profiler::trace('handle-payment', fn (): ?RedirectResponse => $this->paymentService->handlePaymentByOrder($orderId, $data, $context, $finishUrl, $errorUrl));

            return $response ?? new RedirectResponse($finishUrl);
        } catch (PaymentProcessException|InvalidOrderException|UnknownPaymentMethodException) {
            return $this->forwardToRoute('frontend.checkout.finish.page', ['orderId' => $orderId, 'changedPayment' => false, 'paymentFailed' => true]);
        }
    }

    /**
     * @Route("/inquiry/finish", name="frontend.inquiry.finish.page", options={"seo"=false}, defaults={"_noStore"=true}, methods={"GET"})
     */
    public function finishPage(Request $request, SalesChannelContext $context, RequestDataBag $dataBag): Response
    {
        if (!$context->getCustomer()) {
            return $this->redirectToRoute('frontend.inquiry.register.page');
        }

        $page = $this->finishPageLoader->load($request, $context);

        if ($page->isPaymentFailed() === true) {
            return $this->redirectToRoute(
                'frontend.account.edit-order.page',
                [
                    'orderId' => $request->get('orderId'),
                    'error-code' => 'CHECKOUT__UNKNOWN_ERROR',
                ]
            );
        }

        if ($context->getCustomer()->getGuest() && $this->systemConfigService->get('core.cart.logoutGuestAfterCheckout', $context->getSalesChannel()->getId())) {
            $this->logoutRoute->logout($context, $dataBag);
        }

        return $this->renderStorefront('@Storefront/storefront/page/checkout/finish/index.html.twig',
            ['page' => $page, 'isInquiry' => true]
        );
    }

    private function routeNeedsReload(ErrorCollection $cartErrors): bool
    {
        foreach ($cartErrors as $error) {
            if ($error instanceof ShippingMethodChangedError || $error instanceof PaymentMethodChangedError) {
                return true;
            }
        }

        return false;
    }

    private function getAdditionalRegisterValidationDefinitions(DataBag $data, SalesChannelContext $context): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('storefront.confirmation');

        $definition->add('salutationId', new NotBlank());

        if ($this->systemConfigService->get('core.loginRegistration.requireEmailConfirmation', $context->getSalesChannel()->getId())) {
            $definition->add('emailConfirmation', new NotBlank(), new EqualTo([
                'value' => $data->get('email'),
            ]));
        }

        if ($data->has('guest')) {
            return $definition;
        }

        if ($this->systemConfigService->get('core.loginRegistration.requirePasswordConfirmation', $context->getSalesChannel()->getId())) {
            $definition->add('passwordConfirmation', new NotBlank(), new EqualTo([
                'value' => $data->get('password'),
            ]));
        }

        return $definition;
    }

    private function prepareAffiliateTracking(RequestDataBag $data, SessionInterface $session): DataBag
    {
        $affiliateCode = $session->get(AffiliateTrackingListener::AFFILIATE_CODE_KEY);
        $campaignCode = $session->get(AffiliateTrackingListener::CAMPAIGN_CODE_KEY);
        if ($affiliateCode !== null && $campaignCode !== null) {
            $data->add([
                AffiliateTrackingListener::AFFILIATE_CODE_KEY => $affiliateCode,
                AffiliateTrackingListener::CAMPAIGN_CODE_KEY => $campaignCode,
            ]);
        }

        return $data;
    }


    private function addAffiliateTracking(RequestDataBag $dataBag, SessionInterface $session): void
    {
        $affiliateCode = $session->get(AffiliateTrackingListener::AFFILIATE_CODE_KEY);
        $campaignCode = $session->get(AffiliateTrackingListener::CAMPAIGN_CODE_KEY);
        if ($affiliateCode) {
            $dataBag->set(AffiliateTrackingListener::AFFILIATE_CODE_KEY, $affiliateCode);
        }

        if ($campaignCode) {
            $dataBag->set(AffiliateTrackingListener::CAMPAIGN_CODE_KEY, $campaignCode);
        }
    }

    private function isDoubleOptIn(DataBag $data, SalesChannelContext $context): bool
    {
        $creatueCustomerAccount = $data->getBoolean('createCustomerAccount');

        $configKey = $creatueCustomerAccount
            ? 'core.loginRegistration.doubleOptInRegistration'
            : 'core.loginRegistration.doubleOptInGuestOrder';

        $doubleOptInRequired = $this->systemConfigService
            ->get($configKey, $context->getSalesChannel()->getId());

        if (!$doubleOptInRequired) {
            return false;
        }

        if ($creatueCustomerAccount) {
            $this->addFlash(self::SUCCESS, $this->trans('account.optInRegistrationAlert'));

            return true;
        }

        $this->addFlash(self::SUCCESS, $this->trans('account.optInGuestAlert'));

        return true;
    }

    private function getConfirmUrl(SalesChannelContext $context, Request $request): string
    {
        /** @var string $domainUrl */
        $domainUrl = $this->systemConfigService
            ->get('core.loginRegistration.doubleOptInDomain', $context->getSalesChannel()->getId());

        if ($domainUrl) {
            return $domainUrl;
        }

        $domainUrl = $request->attributes->get(RequestTransformer::STOREFRONT_URL);

        if ($domainUrl) {
            return $domainUrl;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('salesChannelId', $context->getSalesChannel()->getId()));
        $criteria->setLimit(1);

        /** @var SalesChannelDomainEntity|null $domain */
        $domain = $this->domainRepository
            ->search($criteria, $context->getContext())
            ->first();

        if (!$domain) {
            throw new SalesChannelDomainNotFoundException($context->getSalesChannel());
        }

        return $domain->getUrl();
    }
}
