<?php

declare(strict_types=1);

namespace CottonArt\Inquiry\Service;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\HttpKernel\KernelInterface;

class InquiryCustomFieldsManagement
{
    public function __construct(
        private readonly KernelInterface $kernel
    ) { }

    public function getOptionValuesByName(string $fieldName): array
    {
        if (empty($customField = $this->getCustomFieldByName($fieldName))) {
            return [];
        }

        $options = current($customField)?->getConfig()['options'];

        $values = [];
        foreach ($options as $option) {
            $values[] = $option['value'];
        }

        return $values;
    }

    public function getCustomFieldByName(string $fieldName): array
    {
        if (empty($customFieldRepo = $this->getCustomFieldRepository())) {
            return [];
        }

        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('name', $fieldName)
        );

        return (array) $customFieldRepo->search($criteria, Context::createDefaultContext())?->getElements() ?? [];
    }

    public function getCustomFields(): object|array|null
    {
        if (empty($customFieldRepo = $this->getCustomFieldRepository())) {
            return [];
        }

        return $customFieldRepo->search(new Criteria(), Context::createDefaultContext())->getElements();
    }

    private function getCustomFieldRepository(): object|array|null
    {
        try {
            return $this->kernel->getContainer()->get('custom_field.repository');
        } catch (\Exception $e) {
            return [];
        }
    }
}