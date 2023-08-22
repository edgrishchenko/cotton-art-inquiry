<?php declare(strict_types=1);

namespace Pix\Inquiry;

use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;
use Pix\Inquiry\Service\InquiryPayment;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Shopware\Core\System\DeliveryTime\DeliveryTimeEntity;


class PixInquiry extends Plugin
{
    public const CUSTOM_FIELD_SET_ID = '99651ebfc1584250b5faf5f08bbb3ea8';
    public const SHIPPING_METHOD_ID = '018a1757317572c7b662cbd50606e053';

    public function install(InstallContext $installContext): void
    {
        $this->addPaymentMethod($installContext->getContext());
        $this->addCustomFields($installContext->getContext());
        $this->addShippingMethod($installContext->getContext());
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        $this->setPaymentMethodIsActive(false, $uninstallContext->getContext());
        $this->removeCustomFields($uninstallContext->getContext());
    }

    public function activate(ActivateContext $activateContext): void
    {
        $this->setPaymentMethodIsActive(true, $activateContext->getContext());
        parent::activate($activateContext);
    }

    public function deactivate(DeactivateContext $deactivateContext): void
    {
        $this->setPaymentMethodIsActive(false, $deactivateContext->getContext());
        parent::deactivate($deactivateContext);
    }

    private function addCustomFields(Context $context): void
    {
        if ($this->customFieldSetExists($context)) {
            return;
        }

        $customFieldSetRepository = $this->container->get('custom_field_set.repository');
        $customFieldSetRepository->create([
            [
                'id' => self::CUSTOM_FIELD_SET_ID,
                'name' => 'custom_pixinquiry_set',
                'global' => true, // set this to true to prevent accidental editing in admin
                'config' => [
                    'label' => [
                        'en-GB' => 'Pix Inquiry',
                        'de-DE' => 'Pix Anfrage',
                    ],
                ],
                'customFields' => [
                    [
                        'name' => 'custom_pixinquiry_file',
                        'type' => CustomFieldTypes::TEXT,
                        'config' => [
                            'label' => [
                                'en-GB' => 'Inquiry Uploaded File',
                                'de-DE' => 'Anfrage hochgeladene Date',
                            ],
                            'customFieldPosition' => 1,
                        ],
                    ],
                ],
                'relations' => [
                    [
                        'entityName' => OrderDefinition::ENTITY_NAME
                    ],
                ],
            ],
        ], $context);
    }

    private function removeCustomFields(Context $context): void
    {
        if (!$this->customFieldSetExists($context)) {
            return;
        }

        $customFieldSetRepository = $this->container->get('custom_field_set.repository');
        $customFieldSetRepository->delete([
            ['id' => self::CUSTOM_FIELD_SET_ID],
        ], $context);
    }

    private function customFieldSetExists(Context $context): bool
    {
        $customFieldSetRepository = $this->container->get('custom_field_set.repository');

        return ($customFieldSetRepository->search(new Criteria([self::CUSTOM_FIELD_SET_ID]), $context)->getTotal() > 0);
    }

    private function addShippingMethod(Context $context): void
    {
        if ($this->shippingMethodExists($context)) {
            return;
        }

        $shippingMethodRepository = $this->container->get('shipping_method.repository');

        $data = [
            'id' => self::SHIPPING_METHOD_ID,
            'active' => true,
            'availabilityRuleId' => $this->getAvailabilityRuleId($context),
            'deliveryTimeId' => $this->getDeliveryTimeId($context),
            'name' => 'Anfrage',
            'prices' => [
                [
                    'name' => 'anfrage',
                    'price' => '0',
                    'currencyId' => Defaults::CURRENCY,
                    'calculation' => 1,
                    'quantityStart' => 1,
                    'currencyPrice' => [
                        [
                            'currencyId' => Defaults::CURRENCY,
                            'net' => 0,
                            'gross' => 0,
                            'linked' => false,
                        ],
                    ],
                ],
            ],
            'translations' => [
                'de-DE' => [
                    'name' => 'Anfrage'
                ],
                'en-GB' => [
                    'name' => 'Inquiry'
                ],
            ],
        ];

        $shippingMethodRepository->create([$data], Context::createDefaultContext());
    }

    private function getAvailabilityRuleId(Context $context): ?string
    {
        $ruleRepository = $this->container->get('rule.repository');

        $ruleCriteria = new Criteria();
        $ruleCriteria->addFilter(new EqualsFilter('name', 'Always valid (Default)'));
        $id = $ruleRepository->searchIds($ruleCriteria, $context)->firstId();
        if ($id !== null) {
            return $id;
        }

        $ruleCriteria = new Criteria();
        $ruleCriteria->setLimit(1);

        return $ruleRepository->searchIds($ruleCriteria, $context)->firstId();
    }

    private function getDeliveryTimeId(Context $context): ?string
    {
        $deliveryTimeRepository = $this->container->get('delivery_time.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('min', 0));
        $criteria->addFilter(new EqualsFilter('max', 0));
        $criteria->addFilter(new EqualsFilter('unit', DeliveryTimeEntity::DELIVERY_TIME_DAY));
        /** @var DeliveryTimeEntity|null $first */
        $first = $deliveryTimeRepository->search($criteria, $context)->first();

        if ($first !== null) {
            return $first->getId();
        }

        $deliveryTimeRepository->create([[
            'min' => 0,
            'max' => 0,
            'unit' => DeliveryTimeEntity::DELIVERY_TIME_DAY,
            'name' => 'Immediately',
            'translations' => [
                'de-DE' => [
                    'name' => 'Sofort',
                ],
                'en-GB' => [
                    'name' => 'Immediately',
                ],
            ],
        ]], $context);

        return $deliveryTimeRepository->searchIds($criteria, $context)->firstId();
    }

    private function shippingMethodExists(Context $context): bool
    {
        $customFieldSetRepository = $this->container->get('shipping_method.repository');

        return ($customFieldSetRepository->search(new Criteria([self::SHIPPING_METHOD_ID]), $context)->getTotal() > 0);
    }

    private function addPaymentMethod(Context $context): void
    {
        $paymentMethodExists = $this->getPaymentMethodId();

        if ($paymentMethodExists) {
            return;
        }

        /** @var PluginIdProvider $pluginIdProvider */
        $pluginIdProvider = $this->container->get(PluginIdProvider::class);
        $pluginId = $pluginIdProvider->getPluginIdByBaseClass(get_class($this), $context);

        $inquiryPaymentData = [
            'handlerIdentifier' => InquiryPayment::class,
            'name' => 'Anfrage',
            'pluginId' => $pluginId,
        ];

        /** @var EntityRepository $paymentRepository */
        $paymentRepository = $this->container->get('payment_method.repository');
        $paymentRepository->create([$inquiryPaymentData], $context);
    }

    private function setPaymentMethodIsActive(bool $active, Context $context): void
    {
        /** @var EntityRepository $paymentRepository */
        $paymentRepository = $this->container->get('payment_method.repository');

        $paymentMethodId = $this->getPaymentMethodId();

        // Payment does not even exist, so nothing to (de-)activate here
        if (!$paymentMethodId) {
            return;
        }

        $paymentMethod = [
            'id' => $paymentMethodId,
            'active' => $active,
        ];

        $paymentRepository->update([$paymentMethod], $context);
    }

    private function getPaymentMethodId(): ?string
    {
        /** @var EntityRepository $paymentRepository */
        $paymentRepository = $this->container->get('payment_method.repository');

        // Fetch ID for update
        $paymentCriteria = (new Criteria())->addFilter(new EqualsFilter('handlerIdentifier', InquiryPayment::class));
        return $paymentRepository->searchIds($paymentCriteria, Context::createDefaultContext())->firstId();
    }
}