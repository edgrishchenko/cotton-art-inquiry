<?php declare(strict_types=1);

namespace Pix\Inquiry\Subscriber;

use Shopware\Core\Framework\Validation\BuildValidationEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderServiceSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'framework.validation.order.create' => 'onBuildOrderValidation'
        ];
    }

    public function onBuildOrderValidation(BuildValidationEvent $event): void
    {
        if (in_array('inquiry-saved', $event->getContext()->getStates())) {
            $event->getDefinition()->set('tos');
        }
    }
}