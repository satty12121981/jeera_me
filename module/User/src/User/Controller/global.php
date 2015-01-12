<?php
/**
 * Global Configuration Override
 *
 * You can use this file for overriding configuration values from modules, etc.
 * You would place values in here that are agnostic to the environment and not
 * sensitive to security.
 *
 * @NOTE: In practice, this file will typically be INCLUDED in your source
 * control, so do not include passwords or other sensitive information in this
 * file.
 */

return array(
    'db' => array(
        'driver'         => 'Pdo',
        'dsn'            => 'mysql:dbname=y2m_jeera_me;host=localhost',
		'username'       => 'y2m_jeera_me',
        'password'       => 'jeera123',
        'driver_options' => array(
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\''
        ),
    ),
	'pathInfo' => array(
        'ROOTPATH'         => "C:/wamp/www/jeera_new/",	
		'UploadPath'       => "C:/wamp/www/jeera_new/public/datagd/",
		'AlbumUploadPath'       => "C:/wamp/www/jeera_new/public/album/",
		'TagCategoryPath'  => "C:/wamp/www/jeera/public/datagd/tag_category/",
		'base_url' =>"http://y2m.ae/development/jeera_me/",
		'fbredirect' =>"http://localhost/jeera_new/user/fbredirect",
    ),
	'image_folders' => array(
        'group'         => "datagd/group/",	
		'tag_category'         => "datagd/tag_category/",
		 
    ),
    'service_manager' => array(
        'factories' => array(
            'Zend\Db\Adapter\Adapter'
                    => 'Zend\Db\Adapter\AdapterServiceFactory',
        ),
    ),
	'session' => array(
        'config' => array(
            'class' => 'Zend\Session\Config\SessionConfig',
            'options' => array(
                'name' => 'myapp',
            ),
        ),
        'storage' => 'Zend\Session\Storage\SessionArrayStorage',
        'validators' => array(
            array(
                'Zend\Session\Validator\RemoteAddr',
                'Zend\Session\Validator\HttpUserAgent',
            ),
        ),
    ),
);
