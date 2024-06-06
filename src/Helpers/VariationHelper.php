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
        $imageList = $itemImageRepository->findByVariationId($variationId);

        $addedImage = $itemImageRepository->upload($imageData);

        if (isset($addedImage['id'])){
            $imageData['imageId'] = $addedImage['id'];
            $imageData['position'] = $position;

            /** @var VariationImageRepositoryContract $variationImageRepository */
            $variationImageRepository = pluginApp(VariationImageRepositoryContract::class);
            /** @var VariationImage $variationImage */
            $variationImage = $variationImageRepository->create($imageData);

            if ( ($variationImage->imageId === $imageData['imageId']) && isset($imageList[$position + 1])){
                /* Drop the image that was previously on the specified position (if exists)
                   If the specified position for the added image is greater than the last position,
                   the new image will be added at the end of the list.*/
                /** @var DeleteResponse $response */
                $response = $itemImageRepository->delete($imageList[$position + 1]['id'])->toArray();
                if ($response['affectedRows'] === 1){
                    return true;
                } else {
                    //the new image was added but the previous could not be deleted
                    $this->getLogger(__METHOD__)
                        ->error(PluginConfiguration::PLUGIN_NAME . '::error.previousImageError',
                            [
                                'response'  => $response
                            ]
                        );
                }
            }
        }
        return false;
    }
}