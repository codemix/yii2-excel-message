<?php
namespace codemix\excelmessage;

use Yii;
use yii\console\Exception;
use yii\console\Controller;
use yii\helpers\Console;
use yii\helpers\VarDumper;
use \PHPExcel;
use \PHPExcel_Worksheet;
use \PHPExcel_IOFactory;
use \PHPExcel_Cell_DataType;

/**
 * Export new translations to Excel files from PHP message files and update PHP
 * message files with new translations from Excel files.
 *
 * Both commands require the same config file that you used to create your PHP
 * message files.
 */
class ExcelMessageController extends Controller
{
    public $defaultAction = 'export';

    /**
     * @var string Comma separated list of languages to process.
     */
    public $languages;

    /**
     * @var string Comma separated list of categories to process.
     */
    public $categories;

    /**
     * @var string Comma separated list of languages to ignore.
     * Ignored if `--languages` option is set.
     */
    public $ignoreLanguages;

    /**
     * @var string Comma separated list of categories to ignore.
     * Ignored if `--categories` option is set.
     */
    public $ignoreCategories;

    /**
     * @var null|string The line height to set on created Excel files.
     * By default the line height is set to auto, but this is broken for LibreOffice Calc.
     * You can try values like `50` here to set a fixed line height instead.
     */
    public $lineHeight;

    /**
     * @inheritdoc
     */
    public function options($actionID)
    {
        $options = ['color', 'languages', 'categories', 'ignoreLanguages', 'ignoreCategories'];
        if ($actionID==='export') {
            $options[] = 'lineHeight';
        }
        return $options;
    }

    /**
     * Creates Excel files with translations from PHP message files.
     *
     * By default this command will go through all configured PHP message files and
     * check for new translations. It will then write those missing translations
     * to an Excel file, using one file per language and one sheet per category.
     *
     * @param string $configFile The path or alias of the message configuration file.
     * @param string $excelDir The path or alias to the output directory for the Excel files.
     * @param string $type The type of messages to include. Either 'new' (default) or 'all'.
     * @throws Exception on failure.
     */
    public function actionExport($configFile, $excelDir, $type = 'new')
    {
        $config = $this->checkArgs($configFile, $excelDir);
        $messages = [];
        $sourceLanguage = Yii::$app->language;
        foreach ($config['languages'] as $language) {
            if (!$this->languageIncluded($language)) {
                $this->stdout("Skipping language $language.\n", Console::FG_YELLOW);
                continue;
            }
            $dir = $config['messagePath'] . DIRECTORY_SEPARATOR . $language;
            foreach (glob("$dir/*.php") as $file) {
                $category = pathinfo($file, PATHINFO_FILENAME);
                if (!$this->categoryIncluded($category)) {
                    $this->stdout("Skipping category $category.\n", Console::FG_YELLOW);
                    continue;
                }
                $this->stdout("Reading $file ... ", Console::FG_GREEN);
                $existing = require($file);
                if ($type==='new') {
                    $existing = array_filter($existing, function ($v) { return $v===''; });
                }
                foreach ($existing as $source => $translation) {
                    if (!isset($messages[$language])) {
                        $messages[$language] = [];
                    }
                    if (!isset($messages[$language][$category])) {
                        $messages[$language][$category] = [];
                    }
                    $messages[$language][$category][$source] = $translation;
                }
                $this->stdout("Done.\n", Console::FG_GREEN);
            }
        }
        if (count($messages)!==0) {
            $this->writeToExcelFiles($messages, $excelDir);
        } else {
            $this->stdout("No new translations found\n", Console::FG_GREEN);
        }
        return self::EXIT_CODE_NORMAL;
    }

    /**
     * Import the translations from Excel files into PHP message files.
     *
     * By default this command will go through all found Excel files in the given directory,
     * read out the non-empty translations and update the respective PHP message files. The
     * files must be in the same structure as created by the export command: One file per
     * language, with the language code as filename, one sheet per category, source is in
     * column A, translation in column B. The first line gets ignored.
     *
     * @param string $configFile The path or alias of the message configuration file.
     * @param string $excelDir The path or alias to the input directory for the Excel files.
     * @param string $extension The Excel file extension. Default is 'xlsx'.
     * @return void
     */
    public function actionImport($configFile, $excelDir, $extension = 'xlsx')
    {
        $config = $this->checkArgs($configFile, $excelDir);
        $messages = [];
        foreach (glob(rtrim($excelDir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'*.'.$extension) as $file) {
            $language = pathinfo($file, PATHINFO_FILENAME);
            if (!$this->languageIncluded($language)) {
                $this->stdout("Skipping language $language.\n", Console::FG_YELLOW);
                continue;
            }
            $excel = PHPExcel_IOFactory::load($file);
            foreach ($excel->getSheetNames() as $category) {
                if (!$this->categoryIncluded($category)) {
                    $this->stdout("Skipping category $category.\n", Console::FG_YELLOW);
                    continue;
                }
                $sheet = $excel->getSheetByName($category);
                $row = 2;
                while (($source = $sheet->getCellByColumnAndRow(0,$row)->getValue())!==null) {
                    $translation = (string)$sheet->getCellByColumnAndRow(1, $row)->getValue();
                    if (trim($translation)!=='') {
                        if (!isset($messages[$language])) {
                            $messages[$language] = [];
                        }
                        if (!isset($messages[$language][$category])) {
                            $messages[$language][$category] = [];
                        }
                        $messages[$language][$category][$source] = $translation;
                    }
                    $row++;
                }
            }
        }
        $this->updateMessageFiles($messages, $config);
    }

    /**
     * Check whether arguments are valid
     *
     * @param string $configFile the path or alias of the configuration file.
     * @param string $excelDir the path or alias to the directory of the Excel files
     * @return void
     */
    protected function checkArgs($configFile, $excelDir)
    {
        $configFile = Yii::getAlias($configFile);
        if (!is_file($configFile)) {
            throw new Exception("The configuration file does not exist: $configFile");
        }
        $excelDir = Yii::getAlias($excelDir);
        if (!is_dir($excelDir)) {
            throw new Exception("The output directory does not exist: $excelDir");
        }

        $config = array_merge([
            'format' => 'php',
        ], require($configFile));

        if (empty($config['format']) || $config['format']!=='php') {
            throw new Exception('Format must be "php".');
        }
        if (!isset($config['messagePath'])) {
            throw new Exception('The configuration file must specify "messagePath".');
        } elseif (!is_dir($config['messagePath'])) {
            throw new Exception("The message path {$config['messagePath']} is not a valid directory.");
        }
        if (empty($config['languages'])) {
            throw new Exception("Languages cannot be empty.");
        }
        return $config;
    }

    /**
     * Write messages to excel files
     *
     * @param array $messages
     * @param string $excelDir output directory
     */
    protected function writeToExcelFiles($messages, $excelDir)
    {
        foreach ($messages as $language => $categories) {
            $file = rtrim($excelDir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$language.'.xlsx';
            $this->stdout("Writing Excel file for $language to $file ... ", Console::FG_GREEN);
            $excel = new PHPExcel();
            $index = 0;
            foreach ($categories as $category => $sources) {
                $sheet = new PHPExcel_Worksheet($excel, $category);
                $excel->addSheet($sheet, $index++);
                $sheet->getColumnDimension('A')->setWidth(60);
                $sheet->getColumnDimension('B')->setWidth(60);
                $sheet->setCellValue('A1', 'Source', PHPExcel_Cell_DataType::TYPE_STRING);
                $sheet->setCellValue('B1', 'Translation', PHPExcel_Cell_DataType::TYPE_STRING);
                $sheet->getStyle('A1:B1')->applyFromArray([
                    'font' => [
                        'bold' => true,
                    ]
                ]);
                $row = 2;
                foreach ($sources as $message => $translation) {
                    $sheet->setCellValue('A'.$row, $message);
                    $sheet->getStyle('A'.$row)->getAlignment()->setWrapText(true);
                    if ($translation!=='') {
                        $sheet->setCellValue('B'.$row, $translation);
                        $sheet->getStyle('B'.$row)->getAlignment()->setWrapText(true);
                    }
                    // This does not work with LibreOffice Calc, see:
                    // https://github.com/PHPOffice/PHPExcel/issues/588
                    $sheet->getRowDimension($row)->setRowHeight($this->lineHeight===null ? -1 : $this->lineHeight);
                    $row++;
                }
            }
            $excel->removeSheetByIndex($index);
            $excel->setActiveSheetIndex(0);
            $writer = PHPExcel_IOFactory::createWriter($excel, "Excel2007");
            $writer->save($file);
            $this->stdout("Done.\n", Console::FG_GREEN);
        }
    }

    /**
     * Update existing message files
     *
     * @param array $messages
     * @param array $config
     */
    protected function updateMessageFiles($messages, $config)
    {
        foreach ($messages as $language => $categories) {
            $this->stdout("Updating translations for $language\n", Console::FG_GREEN);
            $dir = $config['messagePath'] . DIRECTORY_SEPARATOR . $language;
            foreach ($categories as $category => $translations) {
                $file = $dir.DIRECTORY_SEPARATOR.$category.'.php';
                if (!file_exists($file)) {
                    $this->stdout("Category '$category' not found for language '$language' ($file) - Skipping", Console::FG_RED);
                }
                $this->stdout("Updating $file\n");
                $existingMessages = require($file);
                foreach ($translations as $message => $translation) {
                    if (!array_key_exists($message, $existingMessages)) {
                        $this->stdout('Skipping (removed): ', Console::FG_YELLOW);
                        $this->stdout($message."\n");
                    } elseif ($existingMessages[$message]!=='') {
                        $this->stdout('Skipping (exists): ', Console::FG_YELLOW);
                        $this->stdout($message."\n");
                    } else {
                        $existingMessages[$message] = $translation;
                    }
                }
                ksort($existingMessages);
                $emptyMessages = array_filter($existingMessages, function ($v) { return $v===''; });
                $translatedMessages = array_filter($existingMessages, 'strlen');
                $array = VarDumper::export($emptyMessages + $translatedMessages);

                $content = <<<EOD
<?php
/**
 * Message translations.
 *
 * This file is automatically generated by 'yii message/extract' command.
 * It contains the localizable messages extracted from source code.
 * You may modify this file by translating the extracted messages.
 *
 * Each array element represents the translation (value) of a message (key).
 * If the value is empty, the message is considered as not translated.
 * Messages that no longer need translation will have their translations
 * enclosed between a pair of '@@' marks.
 *
 * Message string can be used with plural forms format. Check i18n section
 * of the guide for details.
 *
 * NOTE: this file must be saved in UTF-8 encoding.
 */
return $array;

EOD;

                file_put_contents($file, $content);
            }
            $this->stdout("\n\n");
        }
    }

    /**
     * @param string $language
     * @return bool whether this language should be processed
     */
    protected function languageIncluded($language)
    {
        if ($this->languages===null) {
            return $this->ignoreLanguages===null ? false : in_array($language, explode(',', $this->ignoreLanguages));
        } else {
            return in_array($language, explode(',', $this->languages));
        }
    }

    /**
     * @param string $category
     * @return bool whether this category should be processed
     */
    protected function categoryIncluded($category)
    {
        if ($this->categories===null) {
            return $this->ignoreCategories===null ? true : !in_array($category, explode(',', $this->ignoreCategories));
        } else {
            return in_array($category, explode(',', $this->categories));
        }
    }
}
