<?php

namespace Oro\Bundle\ApiBundle\Processor\Config\GetConfig;

use Doctrine\ORM\Mapping\ClassMetadata;

use Oro\Component\ChainProcessor\ContextInterface;
use Oro\Component\ChainProcessor\ProcessorInterface;
use Oro\Bundle\ApiBundle\Provider\FieldConfigProvider;
use Oro\Bundle\ApiBundle\Provider\RelationConfigProvider;
use Oro\Bundle\ApiBundle\Util\ConfigUtil;
use Oro\Bundle\ApiBundle\Util\DoctrineHelper;
use Oro\Bundle\EntityBundle\Provider\ExclusionProviderInterface;

class NormalizeDefinition implements ProcessorInterface
{
    /** @var DoctrineHelper */
    protected $doctrineHelper;

    /** @var FieldConfigProvider */
    protected $fieldConfigProvider;

    /** @var RelationConfigProvider */
    protected $relationConfigProvider;

    /** @var ExclusionProviderInterface */
    protected $exclusionProvider;

    /**
     * @param DoctrineHelper             $doctrineHelper
     * @param FieldConfigProvider        $fieldConfigProvider
     * @param RelationConfigProvider     $relationConfigProvider
     * @param ExclusionProviderInterface $exclusionProvider
     */
    public function __construct(
        DoctrineHelper $doctrineHelper,
        FieldConfigProvider $fieldConfigProvider,
        RelationConfigProvider $relationConfigProvider,
        ExclusionProviderInterface $exclusionProvider
    ) {
        $this->doctrineHelper         = $doctrineHelper;
        $this->fieldConfigProvider    = $fieldConfigProvider;
        $this->relationConfigProvider = $relationConfigProvider;
        $this->exclusionProvider      = $exclusionProvider;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ContextInterface $context)
    {
        /** @var ConfigContext $context */

        /** @var array|null $definition */
        $definition = $context->getResult();
        if (empty($definition)) {
            // nothing to normalize
            return;
        }

        $fields = ConfigUtil::getFields($definition);

        if (!ConfigUtil::isExcludeAll($definition)) {
            $entityClass = $context->getClassName();
            if ($entityClass && $this->doctrineHelper->isManageableEntity($entityClass)) {
                $fields = $this->completeDefinition(
                    $fields,
                    $entityClass,
                    $context->getVersion(),
                    $context->getRequestType(),
                    $context->getRequestAction()
                );
            }
        }

        $context->setResult(
            [
                ConfigUtil::EXCLUSION_POLICY => ConfigUtil::EXCLUSION_POLICY_ALL,
                ConfigUtil::FIELDS           => $fields
            ]
        );
    }

    /**
     * @param array  $definition
     * @param string $entityClass
     * @param string $version
     * @param string $requestType
     * @param string $requestAction
     *
     * @return array
     */
    protected function completeDefinition(
        array $definition,
        $entityClass,
        $version,
        $requestType,
        $requestAction
    ) {
        $metadata = $this->doctrineHelper->getEntityMetadata($entityClass);

        $definition = $this->getFields($definition, $metadata, $version, $requestType, $requestAction);
        $definition = $this->getAssociations($definition, $metadata, $version, $requestType, $requestAction);

        return $definition;
    }

    /**
     * @param array         $definition
     * @param ClassMetadata $metadata
     * @param string        $version
     * @param string        $requestType
     * @param string        $requestAction
     *
     * @return array
     */
    protected function getFields(
        array $definition,
        ClassMetadata $metadata,
        $version,
        $requestType,
        $requestAction
    ) {
        $fieldNames = $metadata->getFieldNames();
        foreach ($fieldNames as $fieldName) {
            if (array_key_exists($fieldName, $definition)) {
                // already defined
                continue;
            }

            if ($this->exclusionProvider->isIgnoredField($metadata, $fieldName)) {
                $config = [
                    ConfigUtil::EXCLUDE => true
                ];
            } else {
                $config = $this->fieldConfigProvider->getFieldConfig(
                    $metadata->name,
                    $fieldName,
                    $version,
                    $requestType,
                    $requestAction
                );
            }
            $definition[$fieldName] = $config;
        }

        return $definition;
    }

    /**
     * @param array         $definition
     * @param ClassMetadata $metadata
     * @param string        $version
     * @param string        $requestType
     * @param string        $requestAction
     *
     * @return array
     */
    protected function getAssociations(
        array $definition,
        ClassMetadata $metadata,
        $version,
        $requestType,
        $requestAction
    ) {
        $associations = $metadata->getAssociationMappings();
        foreach ($associations as $fieldName => $mapping) {
            if (array_key_exists($fieldName, $definition)) {
                // already defined
                continue;
            }

            $targetEntityClass = $mapping['targetEntity'];
            if ($this->exclusionProvider->isIgnoredEntity($targetEntityClass)
                || $this->exclusionProvider->isIgnoredRelation($metadata, $fieldName)
            ) {
                $config = [
                    ConfigUtil::EXCLUDE => true
                ];
            } else {
                $config = $this->relationConfigProvider->getRelationConfig(
                    $targetEntityClass,
                    $fieldName,
                    $version,
                    $requestType,
                    $requestAction
                );
            }
            $definition[$fieldName] = $config;
        }

        return $definition;
    }
}
