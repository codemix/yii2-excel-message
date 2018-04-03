Yii2 Excel Message
==================

[![Latest Stable Version](https://poser.pugx.org/codemix/yii2-excel-message/v/stable.svg)](https://packagist.org/packages/codemix/yii2-excel-message)
[![Total Downloads](https://poser.pugx.org/codemix/yii2-excel-message/downloads)](https://packagist.org/packages/codemix/yii2-excel-message)
[![Latest Unstable Version](https://poser.pugx.org/codemix/yii2-excel-message/v/unstable.svg)](https://packagist.org/packages/codemix/yii2-excel-message)
[![License](https://poser.pugx.org/codemix/yii2-excel-message/license.svg)](https://packagist.org/packages/codemix/yii2-excel-message)


Translate messages via Excel files.


## Features

With this extension you can export messages from PHP message files to Excel,
send them to your translators and read them back into your message files.

> **Note:** To read and write to and from Excel file, we use the excellent
> [PhpSpreadsheet](https://github.com/PHPOffice/PhpSpreadsheet) package.


## Installation

Install the package with [composer](http://getcomposer.org).

    composer require codemix/yii2-excel-message

Then update your console configuration:

```php
<?php
return [
    'controllerMap' => [
        'excel-message' => [
            'class' => 'codemix\excelmessage\ExcelMessageController'
        ]
    ]
    // ...
];
```

## Creating Excel files with new translations

To create Excel files with new  translations, you need to supply the Yii2 message
configuration and the output directory where the files should be written to:

```
./yii excel-message messages/config.php /output/dir
```

This will create one file per language (filename == language) with one sheet per
category. The source messages are listed in column `A`. Translators should add their
translations to column `B`.

The files will be in `Excel2007` format with `xlsx` extension.

If you want a file with all translations instead, pass `all` as 3rd argument. You can
also export only certain languages or categories:

```
./yii excel-message --languages=de,fr --categories=nav,app messages/config.php /output/dir
```

## Update PHP message files from Excel files

After you receive the Excel files back from your translators you can update your
PHP message files. Again you need to supply the Yii2 message configuration and
the directory path where your Excel files are:

```
./yii excel-message/import messages/config.php /input/dir
```

This will add the new translations to your PHP message files. Yes it's
really that simple.

You can also pass a third parameter with the file extension, the default is `xlsx`
as used by Excel 2007+ files. PhpSpreadsheet should also autodetect other Excel formats 

> **Note:** The files must be provided in the same format as they where created by
> the export:
>  * One file per language with language code as filename
>  * One sheet per category
>  * Source messages in column `A`, translations in column `B`
>  * First line is skipped.

## Options

You can use the following options.

Option  | Description
------- | -----------
`--languages=aa,bb,cc` | Comma separated list of languages to process.
`--categories=cat1,cat2` | Comma separated list of categories to process.
`--ignoreLanguages=aa,bb,cc` | Comma separated list of languages to ignore. This option is ignored if `--languages` is used.
`--ignoreCategories=cat1,cat2` | Comma separated list of categories to ignore. This option is ignored if `--categories` is used.
`--lineHeight=x` | `export` only: The line height to set on the excel file. The default is *auto* but this [does not work](https://github.com/PHPOffice/PHPExcel/issues/588) if the file is openend in LibreOffice Calc. So you can set a fixed line height like `50` here as a workaround.
