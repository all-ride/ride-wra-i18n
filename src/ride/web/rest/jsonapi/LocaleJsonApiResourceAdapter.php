<?php

namespace ride\web\rest\jsonapi;

use ride\library\http\jsonapi\exception\JsonApiException;
use ride\library\http\jsonapi\JsonApiDocument;
use ride\library\http\jsonapi\JsonApiResourceAdapter;
use ride\library\i18n\locale\Locale;

use ride\web\WebApplication;

/**
 * JSON API Resource adapter for the locales
 */
class LocaleJsonApiResourceAdapter implements JsonApiResourceAdapter {

    /**
     * Constructs a new model resource adapter
     * @param \ride\web\WebApplication $web Instance of the web application
     * @param string $type Resource type for the parameters
     * @return null
     */
    public function __construct(WebApplication $web, $type = null) {
        if ($type === null) {
            $type = 'locales';
        }

        $this->web = $web;
        $this->type = $type;
    }

    /**
     * Gets a resource instance for the provided parameter
     * @param mixed $parameter Parameter to adapt
     * @param \ride\library\http\jsonapi\JsonApiDocument $document Document
     * which is requested
     * @param string $relationshipPath dot-separated list of relationship names
     * @return JsonApiResource|null
     */
    public function getResource($locale, JsonApiDocument $document, $relationshipPath = null) {
        if ($locale === null) {
            return null;
        } elseif (!$locale instanceof Locale) {
            throw new JsonApiException('Could not get resource: provided data is not a locale');
        }

        $query = $document->getQuery();
        $api = $document->getApi();
        $id = $locale->getCode();

        $resource = $api->createResource($this->type, $id, $relationshipPath);
        $resource->setLink('self', $this->web->getUrl('api.locales.detail', array('id' => $id)));

        if ($query->isFieldRequested($this->type, 'code')) {
            $resource->setAttribute('code', $locale->getCode());
        }
        if ($query->isFieldRequested($this->type, 'name')) {
            $resource->setAttribute('name', $locale->getName());
        }
        if ($query->isFieldRequested($this->type, 'properties')) {
            $resource->setAttribute('properties', $locale->getProperties());
        }

        return $resource;
    }

}
