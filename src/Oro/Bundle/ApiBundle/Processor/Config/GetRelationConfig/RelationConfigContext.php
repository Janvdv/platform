<?php

namespace Oro\Bundle\ApiBundle\Processor\Config\GetRelationConfig;

use Oro\Bundle\ApiBundle\Processor\Config\ConfigContext;

class RelationConfigContext extends ConfigContext
{
    /** the name of a field */
    const FIELD_NAME = 'field';

    /**
     * Gets the name of a field.
     *
     * @return string|null
     */
    public function getFieldName()
    {
        return $this->get(self::FIELD_NAME);
    }

    /**
     * Sets the name of a field.
     *
     * @param string $fieldName
     */
    public function setFieldName($fieldName)
    {
        $this->set(self::FIELD_NAME, $fieldName);
    }
}
