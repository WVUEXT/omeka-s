<?php
namespace Omeka\Api\Adapter\Entity;

use Doctrine\ORM\QueryBuilder;
use Omeka\Model\Entity\EntityInterface;
use Omeka\Model\Entity\Vocabulary;
use Omeka\Stdlib\ErrorStore;

class PropertyAdapter extends AbstractEntityAdapter
{
    /**
     * {@inheritDoc}
     */
    protected $sortFields = array(
        'id'         => 'id',
        'local_name' => 'localName',
        'label'      => 'label',
        'comment'    => 'comment',
    );

    /**
     * {@inheritDoc}
     */
    public function getResourceName()
    {
        return 'properties';
    }

    /**
     * {@inheritDoc}
     */
    public function getRepresentationClass()
    {
        return 'Omeka\Api\Representation\Entity\PropertyRepresentation';
    }

    /**
     * {@inheritDoc}
     */
    public function getEntityClass()
    {
        return 'Omeka\Model\Entity\Property';
    }

    /**
     * {@inheritDoc}
     */
    public function hydrate(array $data, EntityInterface $entity,
        ErrorStore $errorStore, $isManaged
    ) {
        if (isset($data['o:owner']['o:id'])) {
            $owner = $this->getAdapter('users')
                ->findEntity($data['o:owner']['o:id']);
            $entity->setOwner($owner);
        }
        if (isset($data['o:vocabulary']['o:id'])) {
            $vocabulary = $this->getAdapter('vocabularies')
                ->findEntity($data['o:vocabulary']['o:id']);
            $entity->setVocabulary($vocabulary);
        }
        if (isset($data['o:local_name'])) {
            $entity->setLocalName($data['o:local_name']);
        }
        if (isset($data['o:label'])) {
            $entity->setLabel($data['o:label']);
        }
        if (isset($data['o:comment'])) {
            $entity->setComment($data['o:comment']);
        }

    }

    /**
     * {@inheritDoc}
     */
    public function buildQuery(QueryBuilder $qb, array $query)
    {
        if (isset($query['owner_id'])) {
            $userAlias = $this->createAlias();
            $qb->innerJoin(
                'Omeka\Model\Entity\Property.owner',
                $userAlias
            );
            $qb->andWhere($qb->expr()->eq(
                "$userAlias.id",
                $this->createNamedParameter($qb, $query['owner_id']))
            );
        }
        if (isset($query['vocabulary_id'])) {
            $vocabularyAlias = $this->createAlias();
            $qb->innerJoin(
                'Omeka\Model\Entity\Property.vocabulary',
                $vocabularyAlias
            );
            $qb->andWhere($qb->expr()->eq(
                "$vocabularyAlias.id",
                $this->createNamedParameter($qb, $query['vocabulary_id']))
            );
        }
        if (isset($query['vocabulary_namespace_uri'])) {
            $vocabularyAlias = $this->createAlias();
            $qb->innerJoin(
                'Omeka\Model\Entity\Property.vocabulary',
                $vocabularyAlias
            );
            $qb->andWhere($qb->expr()->eq(
                "$vocabularyAlias.namespace_uri",
                $this->createNamedParameter($qb, $query['vocabulary_namespace_uri']))
            );
        }
        if (isset($query['vocabulary_prefix'])) {
            $vocabularyAlias = $this->createAlias();
            $qb->innerJoin(
                'Omeka\Model\Entity\Property.vocabulary',
                $vocabularyAlias
            );
            $qb->andWhere($qb->expr()->eq(
                "$vocabularyAlias.prefix",
                $this->createNamedParameter($qb, $query['vocabulary_prefix']))
            );
        }

        if (isset($query['vocabulary_prefix'])) {
            $qb->innerJoin(
                    'Omeka\Model\Entity\Property.vocabulary',
                    'Omeka\Model\Entity\Vocabulary'
                    )->andWhere($qb->expr()->eq(
                            'Omeka\Model\Entity\Vocabulary.prefix',
                            $this->createNamedParameter($qb, $query['vocabulary_prefix'])
            ));
        }
        if (isset($query['local_name'])) {
            $qb->andWhere($qb->expr()->eq(
                "Omeka\Model\Entity\Property.localName",
                $this->createNamedParameter($qb, $query['local_name']))
            );
        }
        if (isset($query['term']) && $this->isTerm($query['term'])) {
            list($prefix, $localName) = explode(':', $query['term']);
            $vocabularyAlias = $this->createAlias();
            $qb->innerJoin(
                'Omeka\Model\Entity\Property.vocabulary',
                $vocabularyAlias
            );
            $qb->andWhere($qb->expr()->eq(
                "$vocabularyAlias.prefix",
                $this->createNamedParameter($qb, $prefix))
            );
            $qb->andWhere($qb->expr()->eq(
                "Omeka\Model\Entity\Property.localName",
                $this->createNamedParameter($qb, $localName))
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function validateEntity(EntityInterface $entity, ErrorStore $errorStore,
        $isManaged
    ) {
        // Validate local name
        $localName = $entity->getLocalName();
        if (empty($localName)) {
            $errorStore->addError('o:local_name', 'The local name cannot be empty.');
        }

        // Validate label
        $label = $entity->getLabel();
        if (empty($label)) {
            $errorStore->addError('o:label', 'The label cannot be empty.');
        }

        // Validate vocabulary
        if ($entity->getVocabulary() instanceof Vocabulary) {
            if ($entity->getVocabulary()->getId()) {
                // Vocabulary is persistent. Check for unique local name.
                $criteria = array(
                    'vocabulary' => $entity->getVocabulary(),
                    'localName' => $entity->getLocalName(),
                );
                if (!$this->isUnique($entity, $criteria)) {
                    $errorStore->addError('o:local_name', sprintf(
                        'The local name "%s" is already taken.',
                        $entity->getLocalName()
                    ));
                }
            }
        } else {
            $errorStore->addError('o:vocabulary', 'A vocabulary must be set.');
        }
    }
}
