<?php

namespace ADLImageImport\Services;

use ADLImageImport\Clients\SFTPClient;
use ADLImageImport\Configuration\PluginConfiguration;
use ADLImageImport\Helpers\VariationHelper;
use Plenty\Plugin\Log\Loggable;
use Plenty\Plugin\Translation\Translator;

class ReadFilesService
{
    use Loggable;

    /**
     * @var Translator
     */
    private $translator;

    /**
     * @var SFTPClient
     */
    private $sftpClient;

    /** @var VariationHelper */
    private $variationHelper;

    public function __construct(
        Translator                  $translator,
        SFTPClient                  $sftpClient,
        VariationHelper             $variationHelper
    ) {
        $this->translator       = $translator;
        $this->sftpClient       = $sftpClient;
        $this->variationHelper  = $variationHelper;
    }

    /**
     * @return array|string
     */
    private function getFtpFileNames()
    {
        try {
            $files = $this->sftpClient->readFileNames();
        } catch (\Exception $exception) {
            $this->getLogger(__METHOD__)
                ->error(PluginConfiguration::PLUGIN_NAME . '::error.readFilesError',
                    [
                        'errorMsg'  => $exception->getMessage()
                    ]
                );
            return [];
        }

        return $files;
    }

    private function getFileContents($fileName)
    {
        try {
            $files = $this->sftpClient->downloadFile($fileName);
        } catch (\Exception $exception) {
            $this->getLogger(__METHOD__)
                ->error(PluginConfiguration::PLUGIN_NAME . '::error.readFilesError',
                    [
                        'errorMsg'  => $exception->getMessage()
                    ]
                );
            return [];
        }

        return $files;
    }

    /**
     * @param $fileName
     * @return array
     */
    private function getDataFromFileName($fileName)
    {
        $fileData = [];

        $dataParts = explode('_', $fileName);

        $fileData['fileName'] = time() . $fileName;
        if (count($dataParts) != 2){
            $fileData['error'] = 'File structure corrupt!';
        } else {
            $fileData['externalId'] = $dataParts[0];
            $dataParts = explode('.', $dataParts[1]);
            if (count($dataParts) != 2){
                $fileData['error'] = 'Image position and file extension can not be separated!';
            } else {
                $fileData['imagePosition'] = $dataParts[0];
                $fileData['fileExtension'] = $dataParts[1];
            }
        }
        return $fileData;
    }

    private function deleteFileFromFtp($fileName)
    {
        try {
            $result = $this->sftpClient->deleteFile($fileName);
        } catch (\Exception $exception) {
            return $exception->getMessage();
        }

        return $result;
    }

    /**
     * @return string
     */
    public function processFtpFiles()
    {
        $filesImportedSuccessfully = 0;

        $files = $this->getFtpFileNames();

        foreach ($files as $file){
            $fileImportError = false;
            $fileData = $this->getDataFromFileName($file);
            if (isset($fileData['error'])){
                $this->getLogger(__METHOD__)
                    ->error(PluginConfiguration::PLUGIN_NAME . '::error.readFilesError',
                        [
                            'errorMsg'  => 'Wrong file name',
                            'fileName'  => $file
                        ]
                    );
                continue;
            }

            $variations = $this->variationHelper->getVariationsByExternalId($fileData['externalId']);
            if (($variations == null) || (count($variations) == 0)) {
                $this->getLogger(__METHOD__)
                    ->error(
                        PluginConfiguration::PLUGIN_NAME . '::error.readFilesError',
                        [
                            'errorMsg' => 'There are no variations with this external ID:' . $fileData['externalId'],
                            'fileName' => $file
                        ]
                    );
                continue;
            }

            $fileData['imageData'] = $this->getFileContents($file);

            $logData = [];
            $itemId = null;
            $variationIdList = [];

            foreach ($variations as $variation) {
                if (is_null($variation)) {
                    $this->getLogger(__METHOD__)
                        ->error(
                            PluginConfiguration::PLUGIN_NAME . '::error.readFilesError',
                            [
                                'errorMsg' => 'There is no variation with this external ID:' . $fileData['externalId'],
                                'fileName' => $file
                            ]
                        );
                    $fileImportError = true;
                    break;
                }

                if ($itemId == null){
                    $itemId = $variation['itemId'];
                } else {
                    if ($itemId != $variation['itemId']){
                        $this->getLogger(__METHOD__)
                            ->error(
                                PluginConfiguration::PLUGIN_NAME . '::error.readFilesError',
                                [
                                    'errorMsg' => 'Only one item is allowed with variations that have this external ID:' . $fileData['externalId'],
                                    'fileName' => $file
                                ]
                            );
                        $fileImportError = true;
                        break;
                    }
                }

                $variationIdList[] = $variation['variationId'];

                $logData[] = [
                    'item'  => $variation['itemId'],
                    'variation' => $variation['variationId']
                ];
            }
            if (!$fileImportError){
                $fileImportError = !$this->variationHelper->addImageToMultipleVariations(
                    [
                        'fileType' => $fileData['fileExtension'],
                        'uploadFileName' => $fileData['fileName'],
                        'uploadImageData' => $fileData['imageData'],
                        'itemId' => $itemId,
                        'variationId' => $fileData['variationId'],
                    ],
                    $variationIdList,
                    $fileData['imagePosition']
                );
            }
            if (!$fileImportError){
                $fileData['deleted'] = $this->deleteFileFromFtp($file);
                $filesImportedSuccessfully++;
            }
            $this->getLogger(__METHOD__)
                ->info(
                    PluginConfiguration::PLUGIN_NAME . '::general.infoMessage',
                    [
                        'fileName'  => $file,
                        'variations' => $logData
                    ]
                );
        }

        return $filesImportedSuccessfully . ' out of ' . count($files) . ' files imported successfully.';
    }
}
