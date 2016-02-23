<?php

namespace ride\web\rest\jsonapi;

use ride\library\http\jsonapi\exception\JsonApiException;
use ride\library\http\jsonapi\JsonApiDocument;
use ride\library\http\jsonapi\JsonApiResourceAdapter;

use ride\web\WebApplication;

/**
 * JSON API Resource adapter for the system parameters
 */
class TranslationJsonApiResourceAdapter implements JsonApiResourceAdapter {

    /**
     * Constructs a new model resource adapter
     * @param \ride\web\WebApplication $web Instance of the web application
     * @param string $type Resource type for the parameters
     * @return null
     */
    public function __construct(WebApplication $web, $type = null) {
        if ($type === null) {
            $type = 'translations';
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
    public function getResource($translation, JsonApiDocument $document, $relationshipPath = null) {
        if ($translation === null) {
            return null;
        } elseif (!is_array($translation) || !isset($translation['id']) || !isset($translation['locale']) || !isset($translation['key']) || !isset($translation['value'])) {
            throw new JsonApiException('Could not get resource: provided data is not a translation');
        }

        $query = $document->getQuery();
        $api = $document->getApi();

        $resource = $api->createResource($this->type, $translation['id'], $relationshipPath);
        $resource->setLink('self', $this->web->getUrl('api.translations.detail', array('id' => $translation['id'])));

        if ($query->isFieldRequested($this->type, 'key')) {
            $resource->setAttribute('key', $translation['key']);
        }
        if ($query->isFieldRequested($this->type, 'value')) {
            $resource->setAttribute('value', $translation['value']);
        }

        if ($query->isFieldRequested($this->type, 'locale') && $query->isIncluded($relationshipPath)) {
            $adapter = $api->getResourceAdapter('locales');

            $fieldRelationshipPath = ($relationshipPath ? $relationshipPath . '.' : '') . 'locale';

            $relationshipResource = $adapter->getResource($translation['locale'], $document, $fieldRelationshipPath);

            $relationship = $api->createRelationship();
            $relationship->setResource($relationshipResource);
            $relationship->setLink('self', $this->web->getUrl('api.translations.relationship', array('id' => $translation['locale']->getCode() . '-' . $translation['key'], 'relationship' => 'locale')));
            $relationship->setLink('related', $this->web->getUrl('api.translations.related', array('id' => $translation['locale']->getCode() . '-' . $translation['key'], 'relationship' => 'locale')));

            $resource->setRelationship('locale', $relationship);
        }

        return $resource;
    }

}
