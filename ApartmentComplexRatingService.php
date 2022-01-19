<?php

namespace App\Services;

use App\Admin\Controller\ApartmentComplexRatingController;
use App\Entity\ApartmentComplex;
use App\Entity\District;
use App\Entity\ObjectSection;
use App\Repository\ApartmentComplexRepository;
use App\Repository\ObjectSectionRepository;
use App\Services\Settings\SettingsService;
use Exception;

/**
 * Class ApartmentComplexRatingService
 * @package App\Services
 */
class ApartmentComplexRatingService
{
    /**
     * @var SettingsService
     */
    private $settingsService;

    /**
     * @var ObjectSectionRepository
     */
    private $sectionRepository;
    /**
     * @var ApartmentComplexRepository
     */
    private $complexRepository;

    /**
     * @var ApartmentComplex[]
     */
    private $apartmentComplexes;

    /**
     * @var ApartmentComplexService
     */
    private $apartmentComplexService;

    /**
     * ApartmentComplexRatingService constructor.
     * @param SettingsService $settingsService
     * @param ObjectSectionRepository $sectionRepository
     * @param ApartmentComplexService $apartmentComplexService
     * @param ApartmentComplexRepository $complexRepository
     */
    public function __construct(
        SettingsService $settingsService,
        ObjectSectionRepository $sectionRepository,
        ApartmentComplexService $apartmentComplexService,
        ApartmentComplexRepository $complexRepository)
    {
        $this->settingsService = $settingsService;
        $this->sectionRepository = $sectionRepository;
        $this->complexRepository = $complexRepository;
        $this->apartmentComplexService = $apartmentComplexService;

        $this->apartmentComplexes = $this->complexRepository->findBy(['showCoefs' => true]);
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getRatingData(): array
    {
        $sectionsData = $this->getSectionsData();
        foreach ($this->apartmentComplexes as $apartmentComplex)
        {
            foreach ($sectionsData['sections'] as $keySection => $section)
            {
                if ($this->isDistricts($apartmentComplex, $section)
                    || $this->isSubway($apartmentComplex, $section)
                    || $this->isArchitech($apartmentComplex, $section)
                    || $this->isDeveloper($apartmentComplex, $section)
                ){
                    $sectionsData['sections'][$keySection]['objects'][] = [
                        /* @deprecated start */
                        'id' => $apartmentComplex->getId(),
                        'stars' => $this->apartmentComplexService->getStars($apartmentComplex),
                        'class' => $apartmentComplex->getApartmentComplexType(),
                        'rating' => $apartmentComplex->getCoefResult(),
                        'description' => sprintf('%s %s', $apartmentComplex->getMainDescription(), $apartmentComplex->getSlogan()),
                        'address' => $apartmentComplex->getAddress(),
                        'name' => $apartmentComplex->getName(),
                        /* @deprecated end */
                        'complex' => $apartmentComplex,
                        'image' => $apartmentComplex->getPhotos()->first() ?? false
                    ];
                    break;
                }
            }
        }

        return $sectionsData['sections'];
    }

    /**
     * @return array
     * @throws Exception
     */
    private function getSectionsData(): array
    {
        $sections = ['sections' => []];
        $sectionsParams = $this->settingsService->getParameterValue(ApartmentComplexRatingController::SETTING_APARTMENT_COMPLEX_JOURNAL);
        $sectionIDs = explode(',', $sectionsParams);

        foreach ($sectionIDs as $id)
        {
            $places = ['district' => []];

            /** @var ObjectSection $section */
            $section = $this->sectionRepository->find($id);

            /** @var District $district */
            foreach ($section->getDistrict() as $district)
            {
                $places['district'][] = $district->getId();
            }
            $places['zone'] = $section->getZone() ? $section->getZone()->getId() : '';
            $places['arhitektory'] = $section->getApartmentArchitect() ? $section->getApartmentArchitect() : '';
            $places['metro'] = $section->getApartmentSubway() ? $section->getApartmentSubway()->getId() : '';
            $places['zastrojshhiki'] = $section->getApartmentDeveloper() ? $section->getApartmentDeveloper()->getId() : '';

            $sections['sections'][$id] = [
                'places' => $places,
                'area' => $section->getTextLink(),
                'areamainslogan' => $section->getMainSlogan(),
                'areaslogan' => $section->getSlogan(),
                'objects' => [],
                'category' => $section->getCategory()
            ];
        }

        return $sections;
    }

    /**
     * @param ApartmentComplex $apartmentComplex
     * @param array $section
     * @return bool
     */
    private function isDistricts(ApartmentComplex $apartmentComplex, array $section): bool
    {
        return $apartmentComplex->getDistrict()
            && in_array($apartmentComplex->getDistrict()->getId(), $section['places']['district']);
    }

    /**
     * @param ApartmentComplex $apartmentComplex
     * @param array $section
     * @return bool
     */
    private function isSubway(ApartmentComplex $apartmentComplex, array $section): bool
    {
        return $apartmentComplex->getSubway() && $apartmentComplex->getSubway()->getId() == $section['places']['metro'];
    }

    /**
     * @param ApartmentComplex $apartmentComplex
     * @param array $section
     * @return bool
     */
    private function isArchitech(ApartmentComplex $apartmentComplex, array $section): bool
    {
        return $apartmentComplex->getArchitech()
            && $apartmentComplex->getArchitech()->getId() == $section['places']['arhitektory'];
    }

    /**
     * @param ApartmentComplex $apartmentComplex
     * @param array $section
     * @return bool
     */
    private function isDeveloper(ApartmentComplex $apartmentComplex, array $section): bool
    {
        return $apartmentComplex->getDeveloper()
            && $apartmentComplex->getDeveloper()->getId() == $section['places']['zastrojshhiki'];
    }
}