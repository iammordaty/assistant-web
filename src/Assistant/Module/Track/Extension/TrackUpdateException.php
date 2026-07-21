<?php

namespace Assistant\Module\Track\Extension;

use RuntimeException;

/** Błąd koordynowanej aktualizacji utworu (zapis tagów / rename / zapis do DB) */
final class TrackUpdateException extends RuntimeException
{
}
