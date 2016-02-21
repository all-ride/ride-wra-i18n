<?php

namespace ride\web\rest\controller;

use ride\library\http\jsonapi\JsonApiQuery;
use ride\library\http\jsonapi\JsonApi;
use ride\library\http\Response;
use ride\library\i18n\exception\LocaleNotFoundException;
use ride\library\i18n\I18n;

/**
 * Controller for the translation JSON API interface
 */
class TranslationController extends AbstractResourceJsonApiController {

    /**
     * Hook to perform extra initializing
     * @return null
     */
    protected function initialize() {
        $this->addSupportedExtension(self::EXTENSION_BULK);

        $this->setType('translations');
        $this->setIdField('id');
        $this->setAttribute('key');
        $this->setAttribute('value');
        $this->setRelationship('locale', 'locales', 'code');

        $this->setRoute(self::ROUTE_INDEX, 'api.translations.index');
        $this->setRoute(self::ROUTE_DETAIL, 'api.translations.detail');
        $this->setRoute(self::ROUTE_RELATED, 'api.translations.related');
    }

    /**
     * Sets the I18n instance to this controller
     * @param \ride\library\i18n\I18n $i18n
     * @return null
     */
    public function setI18n(I18n $i18n) {
        $this->i18n = $i18n;
    }

    /**
     * Gets the resources for the provided query
     * @param \ride\library\http\jsonapi\JsonApiQuery $query
     * @param integer $total Total number of entries before pagination
     * @return mixed Array with resource data or false when an error occured
     */
    protected function getResources(JsonApiQuery $query, &$total) {
        $locales = null;
        $translations = array();

        $keyQuery = null;
        $valueQuery = null;

        $filters = $query->getFilters();
        foreach ($filters as $filterName => $filterValue) {
            switch ($filterName) {
                case 'locale':
                    $locales = $filterValue;
                    if ($locales && !is_array($locales)) {
                        $locales = explode(',', $locales);
                    }

                    break;
                case 'key':
                    $keyQuery = $filterValue;

                    break;
                case 'value':
                    $valueQuery = $filterValue;

                    break;
                default:
                    $this->addFilterNotFoundError($this->type, $filterName);

                    break;
            }
        }

        $sorter = $this->createSorter($this->type, array('locale', 'key', 'value'));

        if ($this->document->getErrors()) {
            return false;
        }

        // lookup the requested locales
        if ($locales) {
            foreach ($locales as $index => $locale) {
                try {
                    $locale = $this->i18n->getLocale($locale);

                    $locales[$index] = $locale;
                } catch (LocaleNotFoundException $exception) {
                    $this->getLog()->logException($exception);

                    unset($locales[$index]);
                }
            }
        } else {
            $locales = $this->i18n->getLocales();
        }

        // lookup the translations for the locales
        foreach ($locales as $locale) {
            $translator = $this->i18n->getTranslator($locale);

            $localeTranslations = $translator->getTranslations();
            foreach ($localeTranslations as $key => $value) {
                if ($keyQuery && $this->filterStringValue($keyQuery, $key) === false) {
                    continue;
                } elseif ($valueQuery && $this->filterStringValue($valueQuery, $value) === false) {
                    continue;
                }

                $id = $locale->getCode() . '-' . $key;

                $translations[$id] = array(
                    'id' => $id,
                    'locale' => $locale,
                    'key' => $key,
                    'value' => $value,
                );
            }
        }

        // perform sort
        $translations = $sorter->sort($translations);

        // perform pagination
        $total = count($translations);
        $translations = array_slice($translations, $query->getOffset(), $query->getLimit(100));

        // return
        return $translations;
    }

    /**
     * Gets the resource for the provided id
     * @param string $id Id of the resource
     * @param boolean $addError Set to false to skip adding the error when the
     * resource is not found
     * @return mixed Resource data if found or false when an error occured
     */
    protected function getResource($id, $addError = true) {
        if (!strpos($id, '-')) {
            if ($addError) {
                $this->addResourceNotFoundError($this->resourceType, $id);
            }

            return false;
        }

        list($locale, $key) = explode('-', $id, 2);

        try {
            $locale = $this->i18n->getLocale($locale);
            $translator = $this->i18n->getTranslator($locale);
        } catch (LocaleNotFoundException $exception) {
            if ($addError) {
                $this->addResourceNotFoundError($this->type, $id);
            }

            return false;
        }

        $translation = $translator->getTranslation($key);
        if (!$translation) {
            if ($addError) {
                $this->addResourceNotFoundError($this->type, $id);
            }

            return false;
        }

        return array(
            'id' => $id,
            'locale' => $locale,
            'key' => $key,
            'value' => $translation,
        );
    }

    /**
     * Validates a resource
     * @param mixed $resource Resource data
     * @param string $index
     * @return null
     */
    protected function validateResource($resource, $index = null) {
        if (!$resource['key']) {
            $this->addAttributeValidationError($this->type, 'key', 'is required', $index);
        } elseif (!is_string($resource['key'])) {
            $this->addAttributeValidationError($this->type, 'key', 'should be a string', $index);
        }

        if (!$resource['value']) {
            $this->addAttributeValidationError($this->type, 'value', 'is required', $index);
        } elseif (!is_string($resource['value'])) {
            $this->addAttributeValidationError($this->type, 'value', 'should be a string', $index);
        }

        if (!$resource['locale']) {
            $this->addRelationshipValidationError($this->type, 'locale', 'is required', $index);
        }
    }

    /**
     * Saves a resource to the data store
     * @param mixed $resource Resource data
     * @return null
     */
    protected function saveResource(&$resource) {
        $translator = $this->i18n->getTranslator($resource['locale']);
        $translator->setTranslation($resource['key'], $resource['value']);

        $resource['id'] = $resource['locale']->getCode() . '-' . $resource['key'];
    }

    /**
     * Deletes a resource from the data store
     * @param mixed $resource Resource data
     * @return null
     */
    protected function deleteResource($resource) {
        $translator = $this->i18n->getTranslator($resource['locale']);
        $translator->setTranslation($resource['key'], null);
    }

    /**
     * Gets the relationship resource data with the provided id
     * @param string $relationship Name of the relationship
     * @param string $id Id of the relationship resource
     * @return mixed Relationship resource or null
     */
    protected function getRelationship($relationship, $id) {
        switch($relationship) {
            case 'locale':
                try {
                    $value = $this->i18n->getLocale($id);
                } catch (LocaleNotFoundException $exception) {
                    $this->getLog()->logException($exception);
                }

                break;
        }

        return $value;
    }

    /**
     * Gets a resource out of the submitted data
     * @return mixed
     */
    protected function getResourceFromData($data, $id = null, $index = null) {
        $resource = parent::getResourceFromData($data, $id, $index);

        if ($this->request->isPost() && !$resource['id'] && $resource['locale']) {
            $id = $resource['locale']->getCode() . '-' . $resource['key'];

            $storeResource = $this->getResource($id, false);
            if ($storeResource) {
                return $this->addDataExistsError($index);
            }
        }

        return $resource;
    }

    /**
     * Processes the provided attribute
     * @param mixed $resource Resource data being populated
     * @param string $attribute Name of the attribute
     * @param mixed $value Value of the attribute
     * @return boolean True when valid, false otherwise
     */
    protected function processAttribute($resource, $attribute, $value, $index = null) {
        if ($attribute === 'key' && $resource['id'] && $resource['key'] != $value) {
            $this->addAttributeReadonlyError($this->type, $attribute, $index);

            return false;
        }

        return true;
    }

    /**
     * Processes the provided relationship
     * @param mixed $resource Resource data being populated
     * @param string $attribute Name of the relationship
     * @param mixed $value Value of the relationship
     * @return boolean True when valid, false otherwise
     */
    protected function processRelationship($resource, $relationship, $value, $index = null) {
        if ($relationship == 'locale' && isset($value[0]) || !$value) {
            $this->addRelationshipValidationError($this->type, $relationship, 'cannot be a collection or null', $index);

            return false;
        }

        return true;
    }

    /**
     * Processes the provided relationship data
     * @param mixed $resource Resource data being populated
     * @param string $relationship Name of the relationship
     * @param mixed $value Resource data of the relationship
     * @return boolean True when valid, false otherwise
     */
    protected function processRelationshipData($resource, $relationship, $value, $index = null) {
        if ($relationship == 'locale') {
            if (!$value) {
                $this->addRelationshipValidationError($this->type, $relationship, 'is required');

                return false;
            } elseif ($resource['id'] && $resource['locale']->getCode() != $value->getCode()) {
                $this->addRelationshipReadonlyError($this->type, $relationship, $index);

                return false;
            }
        }

        return true;
    }

}
