<?php

namespace Assistant\Module\Collection\Extension\Validator;

use Assistant\Module\Common\Model\ModelInterface;

/**
 * Interfejs dla walidatorów
 */
interface ValidatorInterface
{
    public function validate(ModelInterface $track): void;
}
