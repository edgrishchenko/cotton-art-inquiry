<?php declare(strict_types=1);

namespace CottonArt\Inquiry\Service;

use GuzzleHttp\Psr7\MimeType;
use Shopware\Core\Content\Mail\Service\AbstractMailService;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mime\Email;
use CottonArt\Inquiry\CottonArtInquiry;

class InquiryMailService extends AbstractMailService
{
    private const INQUIRY_MAIL_TEMPLATE_NAME = 'cottonart_inquiry_mail_template';

    public function __construct(
        private readonly AbstractMailService $mailService,
        private readonly EntityRepository $mailTemplateRepository,
        private readonly SystemConfigService $systemConfigService,
        private readonly RequestStack $requestStack
    ) {
    }

    public function getDecorated(): AbstractMailService
    {
        return $this->mailService;
    }

    public function send(array $data, Context $context, array $templateData = []): ?Email
    {
        if ($this->requestStack->getCurrentRequest()->request->get('inquirySaved')) {
            $inquiryMailTemplate = $this->getMailTemplate($context);
            $orderData = $templateData['order'];

            $shopOwnerEmail = $this->systemConfigService->get('core.basicInformation.email');
            $shopName = $this->systemConfigService->get('core.basicInformation.shopName');

            $uploadedFiles = [];
            if (array_key_exists(CottonArtInquiry::CUSTOM_LOGO_PLACEMENT_FILE, $orderData->getCustomFields())) {
                $uploadedFiles = json_decode($orderData->getCustomFields()[CottonArtInquiry::CUSTOM_LOGO_PLACEMENT_FILE], true);
            }

            $templateData['shopName'] = $shopName;

            $data['recipients'][$shopOwnerEmail] = $shopName;
            $data['senderName'] = $inquiryMailTemplate->getSenderName();
            $data['templateId'] = $inquiryMailTemplate->getId();
            $data['contentHtml'] = $inquiryMailTemplate->getContentHtml();
            $data['contentPlain'] = $inquiryMailTemplate->getContentPlain();
            $data['subject'] = $inquiryMailTemplate->getSubject();

            if (count($uploadedFiles)) {
                $binAttachments = [];

                foreach ($uploadedFiles as $optionKey => $files) {
                    foreach (explode(', ', $files) as $file) {
                        $binAttachments[] = [
                            'content' => file_get_contents($file),
                            'fileName' => basename($file),
                            'mimeType' => MimeType::fromFilename($file)
                        ];
                    }
                }

                $data['binAttachments'] = $binAttachments;
            }
        }

        return $this->mailService->send($data, $context, $templateData);
    }

    private function getMailTemplate(Context $context): ?MailTemplateEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('mailTemplateType.technicalName', self::INQUIRY_MAIL_TEMPLATE_NAME));
        $criteria->setLimit(1);

        return $this->mailTemplateRepository->search($criteria, $context)->first();
    }
}
