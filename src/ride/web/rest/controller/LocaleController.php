<?php

namespace ride\web\rest\controller;

use ride\library\http\jsonapi\JsonApiQuery;
use ride\library\i18n\exception\LocaleNotFoundException;
use ride\library\i18n\I18n;

/**
 * Controller for the locale JSON API interface
 */
class LocaleController extends AbstractResourceJsonApiController {

    /**
     * Hook to perform initializing
     * @return null
     */
    protected function initialize() {
        $this->setType('locales');
        $this->setIdField('code');
        $this->setAttribute('code');
        $this->setAttribute('name');
        $this->setAttribute('properties');

        $this->setRoute(self::ROUTE_INDEX, 'api.locales.index');
        $this->setRoute(self::ROUTE_DETAIL, 'api.locales.detail');
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
        $locales = $this->i18n->getLocales();

        $codeQuery = null;
        $nameQuery = null;

        $filters = $query->getFilters();
        foreach ($filters as $filterName => $filterValue) {
            switch ($filterName) {
                case 'code':
                    $codeQuery = $filterValue;

                    break;
                case 'name':
                    $nameQuery = $filterValue;

                    break;
                default:
                    $this->addFilterNotFoundError($this->type, $filterName);

                    break;
            }
        }

        // perform filter
        if ($codeQuery || $nameQuery) {
            foreach ($locales as $index => $locale) {
                if ($codeQuery && $this->filterStringValue($codeQuery, $locale->getCode()) === false) {
                    unset($locales[$index]);
                } elseif ($nameQuery && $this->filterStringValue($nameQuery, $locale->getName()) === false) {
                    unset($locales[$index]);
                }
            }
        }

        $sorter = $this->createSorter($this->type, array('code', 'name'));

        if ($this->document->getErrors()) {
            return false;
        }

        // perform sort
        $locales = $sorter->sort($locales);

        // perform pagination
        $total = count($locales);
        $locales = array_slice($locales, $query->getOffset(), $query->getLimit(100));

        // return
        return $locales;
    }

    /**
     * Gets the resource for the provided id
     * @param string $id Id of the resource
     * @param boolean $addError Set to false to skip adding the error when the
     * resource is not found
     * @return mixed Resource data if found or false when an error occured
     */
    protected function getResource($id, $addError = true) {
        try {
            $locale = $this->i18n->getLocale($id);
        } catch (LocaleNotFoundException $exception) {
            if ($addError) {
                $this->addResourceNotFoundError($this->resourceType, $id);
            }

            return false;
        }

        return $locale;
    }

}
