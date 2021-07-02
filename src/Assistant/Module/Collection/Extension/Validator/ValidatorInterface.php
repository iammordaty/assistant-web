<?php

namespace Assistant\Module\Collection\Extension\Validator;

use Assistant\Module\Collection\Model\CollectionItemInterface;

/**
 * Interfejs dla walidatorów
 */
interface ValidatorInterface
{
    public function validate(CollectionItemInterface $collectionItem): void;
}
