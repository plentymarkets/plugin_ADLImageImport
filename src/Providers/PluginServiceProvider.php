<?php
namespace ADLImageImport\Providers;

use ErrorException;
use Exception;
use Plenty\Modules\Cron\Services\CronContainer;
use Plenty\Modules\EventProcedures\Services\Entries\ProcedureEntry;
use Plenty\Modules\EventProcedures\Services\EventProceduresService;
use Plenty\Modules\Wizard\Contracts\WizardContainerContract;
use Plenty\Plugin\Application;
use Plenty\Plugin\ServiceProvider;
use ADLImageImport\Assistants\ADLImageImportAssistant;
use ADLImageImport\Crons\ProcessFtpFilesCron;

/**
 * Class PluginServiceProvider
 * @package ADLImageImport\Providers
 */
class PluginServiceProvider extends ServiceProvider
{
    /**
     * @param CronContainer $container
     * @param EventProceduresService $eventProceduresService
     * @param WizardContainerContract $wizardContainerContract
     * @param Application $app
     * @return void
     * @throws ErrorException
     */
    public function boot(
        CronContainer $container,
        EventProceduresService $eventProceduresService,
        WizardContainerContract $wizardContainerContract,
        Application $app
    ) {
        $container->add(CronContainer::DAILY, ProcessFtpFilesCron::class);
        $this->bootProcedures($eventProceduresService);
        $this->getApplication()->register(ADLImageImportRouteServiceProvider::class);
    }

    /**
     * @param  EventProceduresService  $eventProceduresService
     */
    private function bootProcedures(EventProceduresService $eventProceduresService)
    {
    }
}
