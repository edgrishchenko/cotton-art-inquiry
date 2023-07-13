<?php declare(strict_types=1);

namespace Pix\Inquiry\Service;

use Shopware\Core\Content\Mail\Service\AbstractMailService;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\Mime\Email;

class InquiryMailService extends AbstractMailService
{
    private const INQUIRY_MAIL_TEMPLATE_NAME = 'pix_inquiry_mail_template';

    public function __construct(
        private readonly AbstractMailService $mailService,
        private readonly EntityRepository $mailTemplateRepository
    ) {
    }

    public function getDecorated(): AbstractMailService
    {
        return $this->mailService;
    }

    public function send(array $data, Context $context, array $templateData = []): ?Email
    {
        if (in_array('inquiry-saved', $context->getStates())) {
            return null;
        }

        return $this->mailService->send($data, $context, $templateData);
    }

    public function sendInquiryEmailTemplate(SalesChannelContext $salesChannelContext): ?Email
    {
        $mailTemplate = $this->getMailTemplate($salesChannelContext);

        $data = new ParameterBag();
        $data->set(
            'recipients',
            [
                'info@example.com' => 'John Doe'
            ]
        );

        $data->set('senderName', $mailTemplate->getSenderName());

        $data->set('contentHtml', $mailTemplate->getContentHtml());
        $data->set('contentPlain', $mailTemplate->getContentPlain());
        $data->set('subject', $mailTemplate->getSubject());
        $data->set('salesChannelId', $salesChannelContext->getSalesChannel()->getId());

        return $this->mailService->send($data->all(), $salesChannelContext->getContext());
    }

    private function getMailTemplate(SalesChannelContext $salesChannelContext): ?MailTemplateEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('mailTemplateType.technicalName', self::INQUIRY_MAIL_TEMPLATE_NAME));
        $criteria->setLimit(1);

        return $this->mailTemplateRepository->search($criteria, $salesChannelContext->getContext())->first();
    }
}