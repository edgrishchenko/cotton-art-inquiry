<?php declare(strict_types=1);

namespace Pix\Inquiry;

use Shopware\Core\Checkout\Order\OrderDefinition;
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
use Shopware\Core\System\CustomField\CustomFieldTypes;

class PixInquiry extends Plugin
{
    public const CUSTOM_FIELD_SET_ID = '99651ebfc1584250b5faf5f08bbb3ea8';

    public function install(InstallContext $installContext): void
    {
        $this->addPaymentMethod($installContext->getContext());
        $this->addCustomFields($installContext->getContext());
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
                        'de-DE' => 'Pix Inquiry',
                    ],
                ],
                'customFields' => [
                    [
                        'name' => 'custom_pixinquiry_file',
                        'type' => CustomFieldTypes::TEXT,
                        'config' => [
                            'label' => [
                                'en-GB' => 'Inquiry Uploaded File',
                                'de-DE' => 'Inquiry Uploaded File',
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
            'description' => 'Example payment description',
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