<?php

/**
 * @see https://confluence.jetbrains.com/display/PhpStorm/PhpStorm+Advanced+Metadata
 */
namespace PHPSTORM_META {

    override(\Psr\Container\ContainerInterface::get(0), map([
        '' => '@',
    ]));

    override(\DI\Container::get(0), map([
        '' => '@',
    ]));
}
