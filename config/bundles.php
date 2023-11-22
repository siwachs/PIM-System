<?php

use Pimcore\Bundle\BundleGeneratorBundle\PimcoreBundleGeneratorBundle;
use Pimcore\Bundle\CustomReportsBundle\PimcoreCustomReportsBundle;
use Pimcore\Bundle\DataHubBundle\PimcoreDataHubBundle;
use Pimcore\Bundle\DataImporterBundle\PimcoreDataImporterBundle;

return [
    //Twig\Extra\TwigExtraBundle\TwigExtraBundle::class => ['all' => true],
    PimcoreBundleGeneratorBundle::class => ['all' => true],
    PimcoreDataHubBundle::class => ['all' => true],
    PimcoreDataImporterBundle::class => ['all' => true],
    PimcoreCustomReportsBundle::class => ['all' => true],
    RestApiBundle\RestApiBundle::class => ['all' => true],
    EventsListenersBundle\EventsListenersBundle::class => ['all' => true],
];
