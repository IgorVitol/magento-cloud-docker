<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\CloudDocker\Config\Source;

use Illuminate\Config\Repository;
use Magento\CloudDocker\Compose\BuilderFactory;
use Magento\CloudDocker\Compose\DeveloperBuilder;
use Magento\CloudDocker\Compose\ProductionBuilder;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Source for CLI input
 */
class CliSource implements SourceInterface
{
    /**
     * Services.
     */
    public const OPTION_PHP = 'php';
    public const OPTION_NGINX = 'nginx';
    public const OPTION_DB = 'db';
    public const OPTION_EXPOSE_DB_PORT = 'expose-db-port';
    public const OPTION_REDIS = 'redis';
    public const OPTION_ES = 'es';
    public const OPTION_RABBIT_MQ = 'rmq';
    public const OPTION_SELENIUM_VERSION = 'selenium-version';
    public const OPTION_SELENIUM_IMAGE = 'selenium-image';

    /**
     * State modifiers.
     */
    public const OPTION_NODE = 'node';
    public const OPTION_MODE = 'mode';
    public const OPTION_WITH_CRON = 'with-cron';
    public const OPTION_NO_VARNISH = 'no-varnish';
    public const OPTION_WITH_SELENIUM = 'with-selenium';
    public const OPTION_NO_TMP_MOUNTS = 'no-tmp-mounts';
    public const OPTION_SYNC_ENGINE = 'sync-engine';
    public const OPTION_WITH_XDEBUG = 'with-xdebug';

    /**
     * Option key to config name map
     *
     * @var array
     */
    private static $optionsMap = [
        self::OPTION_PHP => self::PHP,
        self::OPTION_DB => self::SERVICES_DB,
        self::OPTION_NGINX => self::SERVICES_NGINX,
        self::OPTION_REDIS => self::SERVICES_REDIS,
        self::OPTION_ES => self::SERVICES_ES,
        self::OPTION_NODE => self::SERVICES_NODE,
        self::OPTION_RABBIT_MQ => self::SERVICES_RMQ,
    ];

    /**
     * Available engines per mode
     *
     * @var array
     */
    private static $enginesMap = [
        BuilderFactory::BUILDER_DEVELOPER => DeveloperBuilder::SYNC_ENGINES_LIST,
        BuilderFactory::BUILDER_PRODUCTION => ProductionBuilder::SYNC_ENGINES_LIST
    ];

    /**
     * @var InputInterface
     */
    private $input;

    /**
     * @param InputInterface $input
     */
    public function __construct(InputInterface $input)
    {
        $this->input = $input;
    }

    /**
     * {@inheritDoc}
     *
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function read(): Repository
    {
        $repository = new Repository();

        $mode = $this->input->getOption(self::OPTION_MODE);
        $syncEngine = $this->input->getOption(self::OPTION_SYNC_ENGINE);

        if ($mode === BuilderFactory::BUILDER_DEVELOPER && $syncEngine === null) {
            $syncEngine = DeveloperBuilder::DEFAULT_SYNC_ENGINE;
        } elseif ($mode === BuilderFactory::BUILDER_PRODUCTION && $syncEngine === null) {
            $syncEngine = ProductionBuilder::DEFAULT_SYNC_ENGINE;
        }

        if (isset(self::$enginesMap[$mode])
            && !in_array($syncEngine, self::$enginesMap[$mode], true)
        ) {
            throw new SourceException(sprintf(
                "File sync engine '%s' is not supported. Available: %s",
                $syncEngine,
                implode(', ', self::$enginesMap[$mode])
            ));
        }

        $repository->set([
            self::CONFIG_SYNC_ENGINE => $syncEngine,
            self::CONFIG_MODE => $mode
        ]);

        foreach (self::$optionsMap as $option => $service) {
            if ($value = $this->input->getOption($option)) {
                $repository->set([
                    $service . '.enabled' => true,
                    $service . '.version' => $value
                ]);
            }
        }

        if ($this->input->getOption(self::OPTION_WITH_SELENIUM)) {
            $repository->set([
                self::SERVICES_SELENIUM_ENABLED => true
            ]);
        }

        if ($seleniumImage = $this->input->getOption(self::OPTION_SELENIUM_IMAGE)) {
            $repository->set([
                self::SERVICES_SELENIUM_ENABLED => true,
                self::SERVICES_SELENIUM_IMAGE => $seleniumImage
            ]);
        }

        if ($seleniumVersion = $this->input->getOption(self::OPTION_SELENIUM_VERSION)) {
            $repository->set([
                self::SERVICES_SELENIUM_ENABLED => true,
                self::SERVICES_SELENIUM_VERSION => $seleniumVersion
            ]);
        }

        if ($this->input->getOption(self::OPTION_NO_TMP_MOUNTS)) {
            $repository->set(self::CONFIG_TMP_MOUNTS, false);
        }

        if ($this->input->getOption(self::OPTION_WITH_CRON)) {
            $repository->set(self::CRON_ENABLED, true);
        }

        if ($this->input->getOption(self::OPTION_NO_VARNISH)) {
            $repository->set(self::SERVICES_VARNISH_ENABLED, false);
        }

        if ($this->input->getOption(self::OPTION_WITH_XDEBUG)) {
            $repository->set([
                self::SERVICES_XDEBUG . '.enabled' => true
            ]);
        }

        return $repository;
    }
}