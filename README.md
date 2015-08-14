Yii2 Excel Message
==================

[![Latest Stable Version](https://poser.pugx.org/codemix/yii2-excel-message/v/stable.svg)](https://packagist.org/packages/codemix/yii2-excel-message)
[![Total Downloads](https://poser.pugx.org/codemix/yii2-excel-message/downloads)](https://packagist.org/packages/codemix/yii2-excel-message)
[![Latest Unstable Version](https://poser.pugx.org/codemix/yii2-excel-message/v/unstable.svg)](https://packagist.org/packages/codemix/yii2-excel-message)
[![HHVM Status](http://hhvm.h4cc.de/badge/yiisoft/yii2-dev.png)](http://hhvm.h4cc.de/package/codemix/yii2-excel-message)
[![License](https://poser.pugx.org/codemix/yii2-excel-message/license.svg)](https://packagist.org/packages/codemix/yii2-excel-message)


Translate new messages via Excel files.

## WIP This extension is still considered alpha stage!

Please use at your own risk - and leave feedback so that we can improve it! Thanks!


## Features

With this extension you can export your new messages from PHP message files to
Excel, send them to your translators and read them back in to update your
message files.


## Installation

Install the package through [composer](http://getcomposer.org):

    composer require codemix/yii2-excel-message

And then add this to your console application configuration:

```php
<?php
return [
    'controllerMap' => [
        'excel-message' => [
            'class' => 'codemix\excelmessage\ExcelMessageController'
        ]
    ]
];
```

Now you're ready to use the extension.

## Creating Excel files with new translations

```
./yii excel-message messages/config.php /output/dir
```

## Update PHP message files from Excel files

```
./yii excel-message/import messages/config.php /input/dir xlsx
```
