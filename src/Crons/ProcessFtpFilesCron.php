<?php

namespace ADLImageImport\Crons;

use ADLImageImport\Configuration\PluginConfiguration;
use ADLImageImport\Services\ReadFilesService;
use Plenty\Modules\Cron\Contracts\CronHandler;
use Plenty\Plugin\ConfigRepository;
use Plenty\Plugin\Log\Loggable;
use Throwable;

class ProcessFtpFilesCron extends CronHandler
{
    use Loggable;

    /**
     * @param ReadFilesService $readFilesService
     * @return void
     */
    public function handle(
        ReadFilesService $readFilesService,
        ConfigRepository $configRepository
    )
    {
        $cronActive = $configRepository->get(PluginConfiguration::PLUGIN_NAME . '.cronactive');
        if ($cronActive) {
            $this->getLogger(__METHOD__)
                ->info(PluginConfiguration::PLUGIN_NAME . '::general.cronStarted');

            $response = $readFilesService->processFtpFiles();

            $this->getLogger(__METHOD__)
                ->info(
                    PluginConfiguration::PLUGIN_NAME . '::general.cronEnded',
                    [
                        'message' => $response
                    ]
                );
        }
    }
}
