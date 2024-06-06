<?php
namespace ADLImageImport\Providers;

use Plenty\Plugin\Routing\ApiRouter;
use Plenty\Plugin\RouteServiceProvider;

/**
 * Class ADLImageImportRouteServiceProvider
 * @package ADLImageImport\Providers
 */
class ADLImageImportRouteServiceProvider extends RouteServiceProvider
{
    /**
     * @param  ApiRouter  $apiRouter
     */
    public function map(ApiRouter $apiRouter)
    {
        $apiRouter->version(['v1'], ['namespace' => 'ADLImageImport\Controllers', 'middleware' => 'oauth'],
            function ($apiRouter) {
                $apiRouter->get('ADLImageImport/import_images/', 'TestController@importImages');
            }
        );
    }
}
