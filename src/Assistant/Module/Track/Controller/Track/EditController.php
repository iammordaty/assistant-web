<?php

namespace Assistant\Module\Track\Controller\Track;

use Assistant\Module\Common\Controller\AbstractController;
use Assistant\Module\Track;

class EditController extends AbstractController
{
    public function edit($pathname)
    {
        $pathname = '/collection' . $pathname;

        $file = null /* pobierz metadane */;

        if (file_exists($pathname) === false) {
            $this->app->redirect(
                // TODO: tylko dla filename, ew. powrót do głównego incoming
                sprintf('%s?query=%s', $this->app->urlFor('search.simple.index'), str_replace(DIRECTORY_SEPARATOR, ' ', $pathname)),
                404
            );
        }

        return $this->app->render(
            '@track/edit/edit.twig',
            [
                'menu' => 'track',
                'pathname' => $pathname,
            ]
        );
    }

    public function save($pathname)
    {

    }
}
