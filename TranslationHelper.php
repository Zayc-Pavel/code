<?php

namespace App\Service\Translate;

use App\Entity\BaseEntity;
use App\Entity\TranslatableEntityInterface;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Gedmo\Translatable\Entity\Repository\TranslationRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Class TranslationHelper
 * @package App\Service\Translate
 */
class TranslationHelper
{
    private const PREFERRED_MAPPING = [
        'ru' => 'en',
        'fr' => 'en',
        'en' => 'ru',
    ];

    /** @var TranslatorInterface */
    private $translator;

    /** @var \Doctrine\Persistence\ObjectRepository|TranslationRepository  */
    private $repository;

    /** @var string */
    private $defaultLocale;

    /** @var array|string[]  */
    private $locales;

    /** @var EntityManagerInterface */
    private $em;

    /** @var string */
    private $currentLocale;

    public function __construct(
        EntityManagerInterface $em,
        TranslatorInterface $translator,
        LocaleService $localeService,
        string $defaultLocale = 'ru',
        array $locales = ['ru', 'en', 'fr']
    ) {
        $this->repository = $em->getRepository('Gedmo\Translatable\Entity\Translation');
        $this->translator = $translator;
        $this->currentLocale = $localeService->getCurrentLocale();
        $this->defaultLocale = $defaultLocale;
        $this->locales = $locales;
        $this->em = $em;
    }

    /**
     * @return TranslatorInterface
     */
    public function getTranslator(): TranslatorInterface
    {
        return $this->translator;
    }

    /**
     * @return string
     */
    public function getDefaultLocale(): string
    {
        return $this->defaultLocale;
    }

    /**
     * @return array|string[]
     */
    public function getLocales(): array
    {
        return $this->locales;
    }

    /**
     * @return string
     */
    public function getCurrentLocale(): string
    {
        return $this->currentLocale;
    }

    /**
     * @param array $values
     * @param array $params
     * @param string|null $domain
     * @return array
     */
    public function translate(array $values, array $params = [], ?string $domain = null): array
    {
        $translations = [];
        foreach ($this->getLocales() as $locale) {
            $translations[$locale] = [];
            foreach ($values as $key => $value) {
                $translations[$locale][$key] = $this->translator->trans($value, $params, $domain, $locale);
            }
        }

        return $translations;
    }

    /**
     * @param TranslatableEntityInterface|BaseEntity $entity
     * @param bool $onlyStoredLocales
     * @return array
     * @throws Exception
     */
    public function getEntityTranslations(TranslatableEntityInterface $entity, bool $onlyStoredLocales = true): array
    {
        $translations = $this->repository->findTranslations($entity);
        $storedLocales = $entity->getStoredLocales();
        $isFillEmptyLocales = $entity->isFillEmptyLocales() && !empty($storedLocales);

        if ($this->currentLocale !== $this->defaultLocale) {
            $entity->setTranslatableLocale($this->defaultLocale);
            $this->em->refresh($entity);

            $translations = $this->addDefaultLocaleTranslations($entity, $translations);

            $entity->setTranslatableLocale($this->currentLocale);
            $this->em->refresh($entity);
        } else {
            $translations = $this->addDefaultLocaleTranslations($entity, $translations);
        }

        foreach ($this->locales as $locale) {
            if (!array_key_exists($locale, $translations)) {
                $translations[$locale] = [];
            }
            foreach ($entity->getTranslatableFields() as $field) {
                if (!array_key_exists($field, $translations[$locale])) {
                    $translations[$locale][$field] = '';
                }
            }
        }

        if ($isFillEmptyLocales && $onlyStoredLocales) {
            foreach ($translations as $locale => $value) {
                if (!in_array($locale, $storedLocales)) {
                    unset($translations[$locale]);
                }
            }
        }

        return $translations;
    }

    /**
     * @param TranslatableEntityInterface|BaseEntity $entity
     * @param array $translations
     * @throws Exception
     */
    public function setEntityTranslations(TranslatableEntityInterface $entity, array $translations)
    {
        $storedLocales = $this->getSharedLocales($translations);
        $translations = $this->normalizeTranslations($translations, $entity->isFillEmptyLocales());
        $entity->setTranslatableLocale($this->defaultLocale);
        $entity->setStoredLocales($storedLocales);

        foreach ($entity->getTranslatableFields() as $field) {
            foreach ($this->locales as $locale) {
                if (!array_key_exists($locale, $translations)) {
                    continue;
                }
                if (!is_array($translations[$locale]) || !array_key_exists($field, $translations[$locale])) {
                    continue;
                }
                if ($locale === $this->defaultLocale) {
                    if (!method_exists($entity, 'set' . ucfirst($field))) {
                        throw new Exception(sprintf('Entity "%s" fault access to method "%s"', get_class($entity), 'set' . ucfirst($field)));
                    }
                    call_user_func([$entity, 'set' . ucfirst($field)], $translations[$locale][$field]);
                } else {
                    if (is_null($translations[$locale][$field])) {
                        $results = $this->repository->findBy([
                            'field' => $field,
                            'locale' => $locale,
                            'foreignKey' => $entity->getId(),
                            'objectClass' => get_class($entity)
                        ]);
                        foreach ($results as $translationEntity) {
                            $this->em->remove($translationEntity);
                        }
                    } else {
                        $this->repository->translate($entity, $field, $locale, $translations[$locale][$field]);
                    }
                }
            }
        }
    }

    /**
     * @param array $translations
     * @param bool $isFillEmptyLocales
     * @return array
     */
    private function normalizeTranslations(array $translations, bool $isFillEmptyLocales = false): array
    {
        if ($isFillEmptyLocales) {
            foreach (self::PREFERRED_MAPPING as $toLocale => $fromLocale) {
                if (!array_key_exists($toLocale, $translations) && array_key_exists($fromLocale, $translations)) {
                    $translations[$toLocale] = $translations[$fromLocale];
                }
            }
        }

        if (!array_key_exists($this->defaultLocale, $translations)) {
            foreach ($this->locales as $locale) {
                if (array_key_exists($locale, $translations)) {
                    $translations[$this->defaultLocale] = $translations[$locale];
                    break;
                }
            }
        }

        return $translations;
    }

    /**
     * @param TranslatableEntityInterface $entity
     * @param array $translations
     * @return array
     * @throws Exception
     */
    private function addDefaultLocaleTranslations(TranslatableEntityInterface $entity, array $translations): array
    {
        foreach ($entity->getTranslatableFields() as $field) {
            if (!method_exists($entity, 'get' . ucfirst($field))) {
                throw new Exception(sprintf('Entity "%s" fault access to method "%s"', get_class($entity), 'get' . ucfirst($field)));
            }
            $translations[$this->defaultLocale][$field] = call_user_func([$entity, 'get' . ucfirst($field)]);
        }
        return $translations;
    }

    /**
     * @param array $translations
     * @return array
     */
    private function getSharedLocales(array $translations): array
    {
        $sharedLocales = [];
        foreach ($translations as $locale => $fields) {
            if (array_filter((array)$fields, function ($value) {return !is_null($value);})) {
                $sharedLocales[] = $locale;
            }
        }
        return $sharedLocales;
    }
}
