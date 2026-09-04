<?php

namespace Assistant\Module\Track\Extension\Similarity\Provider;

use Assistant\Module\Track\Model\Track;

/**
 * Dostawca, który potrafi samodzielnie wskazać kandydatów do porównania.
 *
 * Kryteria zwracane przez getCriteria() zawężają zbiór kandydatów i łączą się iloczynem, więc dostawca
 * opierający się na podobieństwie brzmienia nie ma jak ich użyć - jego kandydaci nie muszą zgadzać się
 * metadanymi. Ścieżki z tego interfejsu poszerzają zbiór kandydatów, zamiast go zawężać.
 */
interface CandidateProviderInterface
{
    /**
     * Ścieżki utworów uznanych przez dostawcę za kandydatów, niezależnie od kryteriów metadanych
     *
     * @return string[]
     */
    public function getCandidatePathnames(Track $baseTrack): array;
}
