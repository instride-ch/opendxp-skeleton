<?php

use Instride\Bundle\OpenDxpMonitoringBundle\OpenDxpMonitoringBundle;
use OpenDxp\Bundle\AdminBundle\OpenDxpAdminBundle;
use OpenDxp\Bundle\ApplicationLoggerBundle\OpenDxpApplicationLoggerBundle;
use OpenDxp\Bundle\SeoBundle\OpenDxpSeoBundle;
use OpenDxp\Bundle\SimpleBackendSearchBundle\OpenDxpSimpleBackendSearchBundle;
use OpenDxp\Bundle\TinymceBundle\OpenDxpTinymceBundle;
use Pentatrion\ViteBundle\PentatrionViteBundle;

return [
    OpenDxpAdminBundle::class => ['all' => true],
    OpenDxpApplicationLoggerBundle::class => ['all' => true],
    OpenDxpMonitoringBundle::class => ['all' => true],
    OpenDxpSeoBundle::class => ['all' => true],
    OpenDxpSimpleBackendSearchBundle::class => ['all' => true],
    OpenDxpTinymceBundle::class => ['all' => true],
    PentatrionViteBundle::class => ['all' => true],
];
