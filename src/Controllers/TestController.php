<?php

namespace ADLImageImport\Controllers;

use ADLImageImport\Services\ReadFilesService;
use Plenty\Plugin\Controller;
use Plenty\Plugin\Log\Loggable;

class TestController extends Controller
{
    use Loggable;

    public function importImages(ReadFilesService $filesService)
    {
        return $filesService->processFtpFiles();
    }
}
