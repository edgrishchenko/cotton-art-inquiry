<?php declare(strict_types=1);

namespace CottonArt\Inquiry\Storefront\Controller;

use CottonArt\Inquiry\CottonArtInquiry;
use CottonArt\Inquiry\Service\FileUploader;
use CottonArt\Inquiry\Service\InquiryCustomFieldsManagement;
use CottonArt\Inquiry\Storefront\Page\Inquiry\Finish\InquiryFinishPageLoader;
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
use Shopware\Core\Content\Media\MediaCollection;
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
use Symfony\Component\Routing\Attribute\Route;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Checkout\Cart\Error\PaymentMethodChangedError;
use Shopware\Storefront\Checkout\Cart\Error\ShippingMethodChangedError;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Shopware\Storefront\Page\Checkout\Register\CheckoutRegisterPageLoader;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints\EqualTo;
use Symfony\Component\Validator\Constraints\NotBlank;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class InquiryController extends StorefrontController
{
    private const REDIRECTED_FROM_SAME_ROUTE = 'redirected';

    public function __construct(
        private readonly AbstractRegisterRoute $registerRoute,
        private readonly AbstractLogoutRoute $logoutRoute,
        private readonly CheckoutRegisterPageLoader $registerPageLoader,
        private readonly InquiryFinishPageLoader $finishPageLoader,
        private readonly CartService $cartService,
        private readonly OrderService $orderService,
        private readonly PaymentService $paymentService,
        private readonly EntityRepository $domainRepository,
        private readonly FileUploader $fileUploader,
        private readonly SystemConfigService $systemConfigService,
        private readonly InquiryCustomFieldsManagement $customFieldsManagement,
        private readonly EntityRepository $mediaFolderRepository,
        private readonly EntityRepository $mediaRepository
    ) {
    }

    #[Route(path: '/inquiry/register', name: 'frontend.inquiry.register.page', options: ['seo' => false], defaults: ['_noStore' => true], methods: ['GET'])]
    public function inquiryRegisterPage(Request $request, RequestDataBag $data, SalesChannelContext $context): Response
    {
        $isCustomerLoggedIn = (bool)$context->getCustomer();
        $allowedMimeTypes = $this->systemConfigService->get('CottonArtInquiry.config.allowedMimeTypes', $context->getSalesChannel()->getId());

        $redirect = $request->get('redirectTo', 'frontend.inquiry.save');
        $errorRoute = $request->attributes->get('_route');

        if ($this->cartService->getCart($context->getToken(), $context)->getLineItems()->count() === 0) {
            return $this->redirectToRoute('frontend.checkout.cart.page');
        }

        $page = $this->registerPageLoader->load($request, $context);
        $cart = $page->getCart();
        $cartErrors = $cart->getErrors();

        $this->addCartErrors($cart);

        if (!$request->query->getBoolean(self::REDIRECTED_FROM_SAME_ROUTE) && $this->routeNeedsReload($cartErrors)) {
            $cartErrors->clear();

            // To prevent redirect loops add the identifier that the request already got redirected from the same origin
            return $this->redirectToRoute(
                'frontend.inquiry.register.page',
                [...$request->query->all(), ...[self::REDIRECTED_FROM_SAME_ROUTE => true]],
            );
        }

        // clearing files for error redirect
        $_FILES = [];

        $logoPlacementOptions = $this->customFieldsManagement->getOptionValuesByName(CottonArtInquiry::CUSTOM_LOGO_PLACEMENT);

        return $this->renderStorefront(
            '@CottonArtInquiry/storefront/page/inquiry/address/index.html.twig',
            [
                'redirectTo' => $redirect,
                'errorRoute' => $errorRoute,
                'page' => $page,
                'data' => $data,
                'isInquiry' => true,
                'isCustomerLoggedIn' => $isCustomerLoggedIn,
                'allowedMimeTypes' => $allowedMimeTypes,
                'finishingMethodOptions' => $this->customFieldsManagement->getOptionValuesByName(CottonArtInquiry::CUSTOM_METHOD_TYPE),
                'logoPlacementOptions' => $logoPlacementOptions,
                'deliveryOptions' => $this->customFieldsManagement->getOptionValuesByName(CottonArtInquiry::CUSTOM_DELIVERY_DURATION),
                'logoMedia' => $this->getLogoPlacementMedia($context, $logoPlacementOptions),
                'uploadFileLogo' => $this->getUploadFileLogo($context)
            ]
        );
    }

    #[Route(path: '/inquiry/register/save', name: 'frontend.inquiry.register.save', defaults: ['_captcha' => true], methods: ['POST'])]
    public function register(Request $request, RequestDataBag $data, SalesChannelContext $context): Response
    {
        try {
            if (!$data->has('differentShippingAddress')) {
                $data->remove('shippingAddress');
            }

            $data->set('storefrontUrl', $this->getConfirmUrl($context, $request));

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

        return $this->forwardToRoute($request->get('redirectTo'));
    }

    #[Route(path: '/inquiry/save', name: 'frontend.inquiry.save', options: ['seo' => false])]
    public function inquirySave(RequestDataBag $data, SalesChannelContext $context, Request $request): Response
    {
        try {
            $request->request->set('inquirySaved', true);
            $context->addState('inquiry-saved');

            $this->parseLogoFiles($context, $request);

            $orderId = Profiler::trace('checkout-order', fn () => $this->orderService->createOrder(new RequestDataBag(['tos' => 'on']), $context));
        } catch (ConstraintViolationException $formViolations) {
            return $this->forwardToRoute('frontend.inquiry.register.page', ['formViolations' => $formViolations]);
        } catch (InvalidCartException|Error|EmptyCartException) {
            $this->addCartErrors(
                $this->cartService->getCart($context->getToken(), $context)
            );

            return $this->forwardToRoute('frontend.inquiry.register.page');
        } catch (UnknownPaymentMethodException|CartException $e) {
            if ($e->getErrorCode() === CartException::CART_PAYMENT_INVALID_ORDER_STORED_CODE && $e->getParameter('orderId')) {
                return $this->forwardToRoute('frontend.inquiry.finish.page', ['orderId' => $e->getParameter('orderId'), 'changedPayment' => false, 'paymentFailed' => true]);
            }
            $message = $this->trans('error.' . $e->getErrorCode());
            $this->addFlash('danger', $message);

            return $this->forwardToRoute('frontend.inquiry.register.page');
        } catch (FileException $e) {
            $this->addFlash('danger', $e->getMessage());

            return $this->forwardToRoute('frontend.inquiry.register.page');
        }

        try {
            $finishUrl = $this->generateUrl('frontend.inquiry.finish.page', ['orderId' => $orderId]);

            $errorUrl = $this->generateUrl('frontend.account.edit-order.page', ['orderId' => $orderId]);

            $response = Profiler::trace('handle-payment', fn (): ?RedirectResponse => $this->paymentService->handlePaymentByOrder($orderId, new RequestDataBag(['tos' => 'on']), $context, $finishUrl, $errorUrl));

            return $response ?? new RedirectResponse($finishUrl);
        } catch (PaymentProcessException|InvalidOrderException|UnknownPaymentMethodException) {
            return $this->forwardToRoute('frontend.inquiry.finish.page', ['orderId' => $orderId, 'changedPayment' => false, 'paymentFailed' => true]);
        }
    }

    #[Route(path: '/inquiry/finish', name: 'frontend.inquiry.finish.page', options: ['seo' => false], defaults: ['_noStore' => true], methods: ['GET'])]
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

    private function parseLogoFiles(SalesChannelContext $context, Request $request): void
    {
        $logoPlacementOptions = $this->customFieldsManagement->getOptionValuesByName(CottonArtInquiry::CUSTOM_LOGO_PLACEMENT);
        $checkedPlacements = $request->request->all(CottonArtInquiry::CUSTOM_LOGO_PLACEMENT);

        $uploadedFiles = [];
        foreach ($logoPlacementOptions as $option) {
            if (!($uploadedFile = $request->files->get(sprintf('%sFile', $option)))
                || !in_array($option, $checkedPlacements)) {
                continue;
            }

            $storefrontUrl = $request->attributes->get(RequestTransformer::SALES_CHANNEL_ABSOLUTE_BASE_URL);
            $uploadedFile = array_map(fn($file): string => $storefrontUrl . $file, $this->fileUploader->upload([$uploadedFile], $context));
            $uploadedFiles[$option] = implode(', ', $uploadedFile);
        }

        $request->request->set(
            CottonArtInquiry::CUSTOM_LOGO_PLACEMENT_FILE,
            json_encode($uploadedFiles)
        );
    }

    private function getUploadFileLogo(SalesChannelContext $context): ?string
    {
        if (!($mediaFolderId = $this->getMediaFolderId($context))) {
            return null;
        }

        return $this->getLogoImage($context, $mediaFolderId, 'file-upload')?->first()?->getUrl();
    }

    private function getLogoPlacementMedia(SalesChannelContext $context, array $logoPlacementOptions): array
    {
        if (!($mediaFolderId = $this->getMediaFolderId($context))) {
            return [];
        }

        $logoMedia = [];
        foreach ($logoPlacementOptions as $option) {
            if (!count($entities = $this->getLogoImage($context, $mediaFolderId, $option))) {
                $logoMedia[$option] = $this->getLogoImage($context, $mediaFolderId, 't-shirt')?->first()?->getUrl();
                continue;
            }

            $logoMedia[$option] = $entities->first()->getUrl();
        }

        return $logoMedia;
    }

    private function getLogoImage(SalesChannelContext $context, string $mediaFolderId, string $filename): MediaCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('fileName', $filename));
        $criteria->addFilter(new EqualsFilter('mediaFolderId', $mediaFolderId));

        return $this->mediaRepository->search($criteria, $context->getContext())->getEntities();
    }

    private function getMediaFolderId(SalesChannelContext $context): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('media_folder.defaultFolder.entity', 'inquiry_logo_placements'));
        $criteria->addAssociation('defaultFolder');
        $criteria->setLimit(1);

        if ($this->mediaFolderRepository->search($criteria, $context->getContext())->getEntities()->count() == 0) {
            return null;
        }

        return $this->mediaFolderRepository->search($criteria, $context->getContext())->getEntities()->first()->id;
    }
}
