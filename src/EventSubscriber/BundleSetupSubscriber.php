<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use OpenDxp\Bundle\AdminBundle\OpenDxpAdminBundle;
use OpenDxp\Bundle\InstallBundle\Event\BundleSetupEvent;
use OpenDxp\Bundle\InstallBundle\Event\InstallEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class BundleSetupSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            InstallEvents::EVENT_BUNDLE_SETUP => [
                ['bundleSetup'],
            ],
        ];
    }

    public function bundleSetup(BundleSetupEvent $event): void
    {
        // Add required OpenDxpAdminBundle
        if (class_exists(OpenDxpAdminBundle::class)) {
            $event->addRequiredBundle(
                'OpenDxpAdminBundle',
                OpenDxpAdminBundle::class,
                true
            );
        }
    }
}
