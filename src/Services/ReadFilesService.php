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
            $files = $this->sftpClient->readFiles();
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

    private function getDataFromFileName($fileName)
    {
        $fileData = [];

        $dataParts = explode('_', $fileName);

        $fileData['fileName'] = $fileName;
        if (count($dataParts) != 2){
            $fileData['error'] = 'File structure corrupt!';
        } else {
            $fileData['variationNumber'] = $dataParts[0];
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
            $files = $this->sftpClient->deleteFile($fileName);
        } catch (\Exception $exception) {
            return $exception->getMessage();
        }

        return $files;
    }

    /**
     * @return string
     */
    public function processFtpFiles()
    {
        $filesImportedSuccessfully = 0;

        $files = $this->getFtpFileNames();

        foreach ($files as $file){
            $fileData = $this->getDataFromFileName($file['fileName']);
            if (isset($fileData['error'])){
                $this->getLogger(__METHOD__)
                    ->error(PluginConfiguration::PLUGIN_NAME . '::error.readFilesError',
                        [
                            'errorMsg'  => 'Wrong file name',
                            'fileName'  => $file['fileName']
                        ]
                    );
            } else {
                $variation = $this->variationHelper->getVariationByNumber($fileData['variationNumber']);
                if (is_null($variation)){
                    $this->getLogger(__METHOD__)
                        ->error(PluginConfiguration::PLUGIN_NAME . '::error.readFilesError',
                            [
                                'errorMsg'  => 'There is no variation with this variation number:' . $fileData['variationNumber'],
                                'fileName'  => $file['fileName']
                            ]
                        );
                } else{
                    $fileData['itemId'] = $variation['itemId'];
                    $fileData['variationId'] = $variation['variationId'];
                    $fileData['imageData'] = $file['contents'];

                    if ($this->variationHelper->addImageToVariation(
                        [
                            'fileType'          => $fileData['fileExtension'],
                            'uploadFileName'    => $fileData['fileName'],
                            'uploadImageData'   => $fileData['imageData'],
                            'itemId'            => $fileData['itemId'],
                            'variationId'       => $fileData['variationId'],
                        ],
                        (int)$fileData['variationId'],
                        (int)$fileData['imagePosition'])
                    ){
                        $fileData['deleted'] = $this->deleteFileFromFtp($file['fileName']);
                        $filesImportedSuccessfully++;
                    } else {
                        $this->getLogger(__METHOD__)
                            ->error(PluginConfiguration::PLUGIN_NAME . '::error.readFilesError',
                                [
                                    'errorMsg'  => 'The image could not be imported!',
                                    'fileName'  => $file['fileName']
                                ]
                            );
                    }
                }

            }
        }

        return $filesImportedSuccessfully . ' out of ' . count($files) . ' files imported successfully.';
    }
}
