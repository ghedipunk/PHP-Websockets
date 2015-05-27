<?php

require_once('core/autoload');

if (file_exists('config/settings.ini'))
{
    \Phpws\Core\GlobalConfig::setConfigFile('config/settings.ini');
}
