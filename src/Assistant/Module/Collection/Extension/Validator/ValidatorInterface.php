<?php

namespace Assistant\Module\Collection\Extension\Validator;

use Assistant\Module\Common\Model\CollectionItemInterface;

/**
 * Interfejs dla walidatorów
 */
interface ValidatorInterface
{
    public function validate(CollectionItemInterface $collectionItem): void;
}
