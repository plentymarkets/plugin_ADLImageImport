<?php

namespace ADLImageImport\Helpers;
use ADLImageImport\Configuration\PluginConfiguration;
use Plenty\Modules\Item\DataLayer\Models\VariationImage;
use Plenty\Modules\Item\ItemImage\Contracts\ItemImageRepositoryContract;
use Plenty\Modules\Item\VariationImage\Contracts\VariationImageRepositoryContract;
use Plenty\Modules\Item\ItemImage\Models\ItemImage;
use Plenty\Modules\Item\Variation\Contracts\VariationLookupRepositoryContract;
use Plenty\Plugin\Log\Loggable;
use Plenty\Repositories\Models\DeleteResponse;

class VariationHelper
{
    use Loggable;

    /**
     * @var VariationLookupRepositoryContract
     */
    private $variationLookupRepository;

    public function __construct(VariationLookupRepositoryContract $variationLookupRepository)
    {
        $this->variationLookupRepository = $variationLookupRepository;
    }

    /**
     * @param $variationNumber
     * @return mixed|null
     */
    public function getVariationByNumber($variationNumber)
    {
        $this->variationLookupRepository->hasNumber($variationNumber);
        $lookupResult = $this->variationLookupRepository->limit(1)->lookup();

        if (!empty($lookupResult)) {
            return $lookupResult[0];
        }

        return null;
    }

    /**
     * @param $externalId
     * @return mixed|null
     */
    public function getVariationsByExternalId($externalId)
    {
        $this->variationLookupRepository->hasExternalId($externalId);
        $lookupResult = $this->variationLookupRepository->limit(10000)->lookup();

        if (!empty($lookupResult)) {
            return $lookupResult;
        }

        return null;
    }

    /**
     * @param array $imageData
     * @param int $variationId
     * @param int $position
     * @return bool
     */
    public function addImageToVariation(array $imageData, int $variationId, int $position): bool
    {
        /** @var ItemImageRepositoryContract $itemImageRepository */
        $itemImageRepository = pluginApp(ItemImageRepositoryContract::class);

        /** @var ItemImage[] $imageList */
        $imageList = $itemImageRepository->findByItemId($imageData['itemId']);

        $imageData['position'] = $position;
        $addedImage = $itemImageRepository->upload($imageData);

        if (isset($addedImage['id'])){
            $imageData['imageId'] = $addedImage['id'];

            /** @var VariationImageRepositoryContract $variationImageRepository */
            $variationImageRepository = pluginApp(VariationImageRepositoryContract::class);
            /** @var VariationImage $variationImage */
            $variationImage = $variationImageRepository->create($imageData);

            if ($variationImage->imageId === $imageData['imageId']){
                /* Drop the image that was previously on the specified position (if exists)
                   If the specified position for the added image is greater than the last position,
                   the new image will be added at the end of the list.*/
                /** @var DeleteResponse $response */
                foreach ($imageList as $image){
                    if ($image['position'] == $imageData['position']){
                        $response = $itemImageRepository->delete($image['id'])->toArray();
                        if ($response['affectedRows'] !== 1){
                            $this->getLogger(__METHOD__)
                                ->error(PluginConfiguration::PLUGIN_NAME . '::error.previousImageError',
                                    [
                                        'response'  => $response
                                    ]
                            );
                        }
                        break;
                    }
                }
                return true;
            }
        }
        return false;
    }

    public function addImageToMultipleVariations(array $imageData, array $variationList, int $position): bool
    {
        /** @var ItemImageRepositoryContract $itemImageRepository */
        $itemImageRepository = pluginApp(ItemImageRepositoryContract::class);

        /** @var ItemImage[] $imageList */
        $imageList = $itemImageRepository->findByItemId($imageData['itemId']);

        $imageData['position'] = $position;
        $addedImage = $itemImageRepository->upload($imageData);

        if (isset($addedImage['id'])){
            $imageData['imageId'] = $addedImage['id'];

            /** @var VariationImageRepositoryContract $variationImageRepository */
            $variationImageRepository = pluginApp(VariationImageRepositoryContract::class);

            foreach ($variationList as $variationId) {
                $imageData['variationId'] = $variationId;

                /** @var VariationImage $variationImage */
                $variationImage = $variationImageRepository->create($imageData);

                if ($variationImage->imageId !== $imageData['imageId']){
                    $this->getLogger(__METHOD__)
                        ->error(PluginConfiguration::PLUGIN_NAME . '::error.variationImageNotLinked',
                            [
                                'variationId'   => $variationId,
                                'itemId'        => $imageData['itemId'],
                                'file'          => $imageData['uploadFileName']
                            ]
                        );
                    return false;
                }
            }
            /* Drop the image that was previously on the specified position (if exists)
               If the specified position for the added image is greater than the last position,
               the new image will be added at the end of the list.*/
            /** @var DeleteResponse $response */
            foreach ($imageList as $image){
                if ($image['position'] == $imageData['position']){
                    $response = $itemImageRepository->delete($image['id'])->toArray();
                    if ($response['affectedRows'] !== 1){
                        $this->getLogger(__METHOD__)
                            ->error(PluginConfiguration::PLUGIN_NAME . '::error.previousImageError',
                                [
                                    'response'  => $response
                                ]
                            );
                    }
                    break;
                }
            }
            return true;
        }
        return false;
    }
}